<?php

namespace Lauzis\WpPackages\Llm;

/**
 * Provider-agnostic LLM caller.
 *
 * Owns everything that is the same whoever is asking: choosing a provider,
 * credentials, endpoints, models, timeouts, request shape and pulling the text
 * back out of each provider's response envelope.
 *
 * It deliberately owns nothing about *what* is being asked. The prompt, the
 * response schema and what to do with the result stay in the plugin — splecheh
 * wants {original, fixed, explanation} per sentence, a translation plugin wants
 * something else entirely.
 *
 * Settings are read from the plugin's own settings page by bare id, so a plugin
 * that maps the fields onto established option keys keeps working.
 */
class Client {

	/** Providers that talk HTTP, in the order they appear in the settings field. */
	const HTTP_PROVIDERS = array( 'openai', 'claude', 'gemini' );

	/** Seconds subtracted from the command timeout when handing it to the wrapper. */
	const WRAPPER_TIMEOUT_MARGIN = 5;

	/** @var string */
	private $slug;

	/** @var array<string, string> Bare setting id => override. */
	private $settings;

	/** @var array<string, string> Provider => model id. */
	private $models;

	/** @var int Request timeout in seconds for HTTP providers. */
	private $timeout;

	/** @var string Key the content is sent under to a commandline provider. */
	private $payload_key;

	/**
	 * @param string $slug   Plugin slug, used to reach its settings page.
	 * @param array  $config {
	 *     @type array $settings Bare id => value, bypassing the settings page.
	 *                           Mainly for tests and one-off overrides.
	 *     @type array $models   Provider => model id, overriding the defaults.
	 *     @type int   $timeout  HTTP request timeout in seconds. Default 60.
	 *     @type string $payload_key Key the content is sent under in the JSON
	 *                           argument given to a commandline provider.
	 *                           Default 'content'.
	 * }
	 */
	public function __construct( $slug, array $config = array() ) {
		$this->slug     = $slug;
		$this->settings = isset( $config['settings'] ) ? $config['settings'] : array();
		$this->timeout  = isset( $config['timeout'] ) ? (int) $config['timeout'] : 60;

		// The JSON argument handed to a command is a contract with whatever
		// script the user has configured. A plugin that already has scripts in
		// the wild names its own key rather than breaking them.
		$this->payload_key = isset( $config['payload_key'] ) ? $config['payload_key'] : 'content';

		// Defaults carried over verbatim from splecheh, which is where this code
		// came from. Override per plugin rather than editing them here.
		$this->models = array_merge(
			array(
				'openai' => 'gpt-4o-mini',
				'claude' => 'claude-3-5-haiku-latest',
				'gemini' => 'gemini-1.5-flash',
			),
			isset( $config['models'] ) ? $config['models'] : array()
		);
	}

	/**
	 * Sends a prompt and returns the model's raw text.
	 *
	 * Parsing is the caller's job — use Llm\Json::extract_array() when the
	 * expected shape is a JSON array.
	 *
	 * @param string $prompt  System instruction.
	 * @param mixed  $content User content. Arrays are JSON-encoded.
	 * @param array  $context Extra values passed to the commandline provider,
	 *                        which receives the whole thing as one JSON argument.
	 * @return string|\WP_Error Raw response text.
	 */
	public function complete( $prompt, $content, array $context = array() ) {
		$provider = $this->provider();

		if ( 'commandline' === $provider ) {
			return $this->via_command( $prompt, $content, $context );
		}

		if ( ! in_array( $provider, self::HTTP_PROVIDERS, true ) ) {
			return new \WP_Error( 'llm_unknown_provider', sprintf( 'Unknown LLM provider: %s', $provider ) );
		}

		$key = $this->setting( 'llm_access_key' );

		if ( '' === (string) $key ) {
			return new \WP_Error(
				'llm_no_access_key',
				sprintf( 'No access key is configured for the %s provider.', $provider )
			);
		}

		$body = is_string( $content ) ? $content : (string) wp_json_encode( $content );

		switch ( $provider ) {
			case 'openai':
				return $this->via_openai( $prompt, $body, $key );
			case 'claude':
				return $this->via_claude( $prompt, $body, $key );
			default:
				return $this->via_gemini( $prompt, $body, $key );
		}
	}

	/** The configured provider, defaulting to the commandline. */
	public function provider() {
		$provider = (string) $this->setting( 'llm_provider' );

		return '' === $provider ? 'commandline' : $provider;
	}

	/**
	 * A human-readable label for the model in use, for status output.
	 *
	 * @return string
	 */
	public function model_label() {
		$provider = $this->provider();

		if ( 'commandline' === $provider ) {
			$model = (string) $this->setting( 'llm_model' );

			return '' !== $model ? $model : $this->extract_model_from_command( (string) $this->setting( 'llm_command' ) );
		}

		return isset( $this->models[ $provider ] ) ? $this->models[ $provider ] : $provider;
	}

	// ------------------------------------------------------------------ providers

	/** OpenAI Chat Completions. */
	private function via_openai( $prompt, $body, $key ) {
		$response = wp_remote_post(
			$this->endpoint( 'https://api.openai.com/v1/chat/completions' ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => $this->models['openai'],
						'messages' => array(
							array( 'role' => 'system', 'content' => $prompt ),
							array( 'role' => 'user', 'content' => $body ),
						),
					)
				),
				'timeout' => $this->timeout,
			)
		);

		return $this->text_from( $response, array( 'choices', 0, 'message', 'content' ) );
	}

	/** Anthropic Messages API. */
	private function via_claude( $prompt, $body, $key ) {
		$response = wp_remote_post(
			$this->endpoint( 'https://api.anthropic.com/v1/messages' ),
			array(
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $this->models['claude'],
						'max_tokens' => 4096,
						'system'     => $prompt,
						'messages'   => array(
							array( 'role' => 'user', 'content' => $body ),
						),
					)
				),
				'timeout' => $this->timeout,
			)
		);

		return $this->text_from( $response, array( 'content', 0, 'text' ) );
	}

	/** Gemini generateContent. */
	private function via_gemini( $prompt, $body, $key ) {
		$model    = $this->models['gemini'];
		$endpoint = $this->endpoint( "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent" );

		$response = wp_remote_post(
			add_query_arg( 'key', $key, $endpoint ),
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents'          => array(
							array( 'parts' => array( array( 'text' => $body ) ) ),
						),
						'systemInstruction' => array(
							'parts' => array( array( 'text' => $prompt ) ),
						),
					)
				),
				'timeout' => $this->timeout,
			)
		);

		return $this->text_from( $response, array( 'candidates', 0, 'content', 'parts', 0, 'text' ) );
	}

	/**
	 * Runs a local command, handing it {prompt, content, ...context} as a single
	 * shell-escaped JSON argument and reading its stdout.
	 *
	 * Keeps credentials out of WordPress: the script owns its own.
	 *
	 * @return string|\WP_Error
	 */
	private function via_command( $prompt, $content, array $context ) {
		$command = trim( (string) $this->setting( 'llm_command' ) );

		if ( '' === $command ) {
			return new \WP_Error( 'llm_no_command', 'No commandline command is configured.' );
		}

		if ( ! class_exists( '\Symfony\Component\Process\Process' ) ) {
			return new \WP_Error( 'llm_no_process', 'symfony/process is required for the commandline provider.' );
		}

		$timeout = (float) $this->setting( 'llm_timeout', 60 );
		$command = $this->with_wrapper_timeout( $command, $timeout );
		$payload = (string) wp_json_encode(
			array_merge( $context, array( 'prompt' => $prompt, $this->payload_key => $content ) )
		);

		try {
			$process = \Symfony\Component\Process\Process::fromShellCommandline( $command . ' ' . escapeshellarg( $payload ) );
			$process->setTimeout( $timeout );
			$exit_code = $process->run();
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'llm_command_failed', $e->getMessage() );
		}

		if ( 0 !== $exit_code ) {
			return new \WP_Error(
				'llm_command_failed',
				sprintf( 'Command exited with code %d: %s', $exit_code, trim( $process->getErrorOutput() ) )
			);
		}

		return $process->getOutput();
	}

	// -------------------------------------------------------------------- helpers

	/**
	 * Appends --timeout to the bundled wrapper, leaving a margin so the wrapper
	 * gives up before the surrounding process does and can report why.
	 *
	 * @param string $command
	 * @param float  $timeout
	 * @return string
	 */
	public function with_wrapper_timeout( $command, $timeout ) {
		if ( false === strpos( $command, 'llm-wrapper.php' ) || preg_match( '/--timeout[= ]/', $command ) ) {
			return $command;
		}

		return $command . ' --timeout ' . max( 1, (int) round( $timeout ) - self::WRAPPER_TIMEOUT_MARGIN );
	}

	/**
	 * Pulls the model's text out of a provider's response envelope.
	 *
	 * @param array|\WP_Error $response
	 * @param array           $path Keys to walk in the decoded body.
	 * @return string|\WP_Error
	 */
	private function text_from( $response, array $path ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( $code >= 400 ) {
			return new \WP_Error(
				'llm_http_error',
				sprintf( 'Provider returned HTTP %d: %s', $code, Json::describe( $body ) )
			);
		}

		$data = json_decode( $body, true );
		$node = $data;

		foreach ( $path as $key ) {
			if ( ! is_array( $node ) || ! isset( $node[ $key ] ) ) {
				return new \WP_Error(
					'llm_bad_response',
					sprintf( 'Unexpected response shape: %s', Json::describe( $body ) )
				);
			}

			$node = $node[ $key ];
		}

		return (string) $node;
	}

	/** The configured endpoint override, or the provider's default. */
	private function endpoint( $default ) {
		$endpoint = trim( (string) $this->setting( 'llm_endpoint' ) );

		return '' === $endpoint ? $default : $endpoint;
	}

	/**
	 * Reads a setting: an explicit override first, then the plugin's settings
	 * page, then the supplied fallback.
	 *
	 * @param string $bare_id
	 * @param mixed  $fallback
	 * @return mixed
	 */
	private function setting( $bare_id, $fallback = '' ) {
		if ( array_key_exists( $bare_id, $this->settings ) ) {
			return $this->settings[ $bare_id ];
		}

		if ( ! class_exists( 'WpPackages_Registry' ) ) {
			return $fallback;
		}

		return \WpPackages_Registry::settings( $this->slug )->get( $bare_id, $fallback );
	}

	/**
	 * Best-effort model name from a wrapper command, for display only.
	 *
	 * @param string $command
	 * @return string
	 */
	private function extract_model_from_command( $command ) {
		if ( preg_match( '/--model[= ]([^\s]+)/', $command, $m ) ) {
			return $m[1];
		}

		return 'commandline';
	}
}
