<?php

namespace SeoAudit;
/**
 * ChatGptApi Class
 */
class ChatGptApi
{
    /**
     * @var string The authorization token for the API
     */
    private $authorization;

    /**
     * @var string The endpoint URL for the OpenAI.com API
     */
    private $endpoint_create_assistant;
    private $endpoint_create_thread;
    private $endpoint_message;
    private string $chat_gpt_version;
    private string $instructions;
    private string $instructions_keywords;
    private string $instructions_keywords_force;
    private string $instructions_for_run;

    /**ChatGptApi
     * ChatGptApi constructor.
     */
    public function __construct()
    {
        $test_mode = carbon_get_theme_option('yolsa_test_mode');
        $token = carbon_get_theme_option('yolsa_test_token');
        $version = carbon_get_theme_option('yolsa_api_version');
        if (!$test_mode) {
            $token = carbon_get_theme_option('yolsa_live_token');
        }

        // This token is not real, in case you were thinking what I'm thinking...
        $this->authorization = $token;
        $this->endpoint_create_assistant = 'https://api.openai.com/v1/assistants';
        $this->endpoint_create_thread = 'https://api.openai.com/v1/threads';
        $this->instructions = carbon_get_theme_option('assistant_instructions') ?? 'You are a Search Engine Optimization expert. Write a meta description for the given text for SEO purposes. The summary should be a description of the article. It must be under 200 characters!!! The summary should always be written in the same language as the article itself is.';
        $this->instructions_keywords = carbon_get_theme_option('assistant_keyword_instructions') ?? 'Take into consideration using these keywords in the meta description:';
        $this->instructions_keywords_force = carbon_get_theme_option('assistant_keyword_instructions_force') ?? 'Description MUST include these words, phrases:';
        $this->instructions_for_run = carbon_get_theme_option('assistant_run_instructions') ?? 'Please generate the meta description in same language as provided text. It must be under 200 symbols.';


        $this->chat_gpt_version = $version ?? "gpt-4";
    }

    public function create_assistant(string $assistant_id, string $instructions, string $name = 'Sean'): array|bool
    {
        $data = [
            'instructions' => $instructions,
            'name' => $name,
            'model' => $this->chat_gpt_version
        ];

        $response = self::curl($this->endpoint_create_assistant, $data);
        if ($response) {
            \SeoAudit\Helpers::save_json($assistant_id, $response);
            return $response;
        }
        return false;
    }

    public function get_assistant(string $instructions): array|bool
    {

        $assistant_id = \SeoAudit\Helpers::string_to_id($instructions);
        $assistant_data = \SeoAudit\Helpers::get_json($assistant_id);
        if ($assistant_data) {
            return $assistant_data;
        } else {
            return $this->create_assistant($assistant_id, $instructions);
        }
    }

    private function curl($request_url, array|bool $data = false, $request_type = 'GET'): array|bool|null
    {
        // Set headers for the API request
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->authorization,
            "OpenAI-Beta: assistants=v2"
        ];

        // Send the request to the API using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        if ($request_type === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        if ($request_type === "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
//
//        // Check for errors in the API response
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
//            throw new Exception('Error sending the message: ' . $error);
            return false;
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    public function create_thread(): array|bool
    {
        $data = [];
        $thread = $this->curl($this->endpoint_create_thread, $data, 'POST');
        if ($thread) {
            \SeoAudit\Helpers::save_json('thread', $thread);
            return $thread;
        }
        return false;
    }

    public function get_thread(): array
    {
        $thread_data = \SeoAudit\Helpers::get_json('thread');
        if ($thread_data) {
            return $thread_data;
        }
        return $this->create_thread();
    }

    /**
     * Send a message to the API and return the response.
     *
     * @param string $content The message to send
     * @return string The response message
     * @throws Exception If there is an error in sending the message via cURL
     */
    public function generate_meta_description(string $content, array $keywords, bool $forceKeywords = false, $lang = ""): string|bool|array
    {
        $debug = [];
        // Prepare data for sending

        $resultMessage = "nothing";

        $consideration = '';
        if (count($keywords) > 0) {
            $consideration = $this->instructions_keywords . ':"' . implode(', ', $keywords) . '". Write meta description in locale : ' . $lang;

            if ($forceKeywords) {
                $consideration = $this->instructions_keywords_force . '"' . implode('", "', $keywords) . '". Write meta description in locale : ' . $lang;;
            }
        }

        $rules = $this->instructions;

        $assistant = $this->get_assistant($rules);
        $assistant_id = $assistant['id'];

        $debug['assistant_id'] = $assistant_id;
        $debug['rules'] = $rules;
        $debug['consideration'] = $consideration;
        $debug['keywords'] = $keywords;
        $debug['authorization'] = $this->authorization;
        $debug['content'] = $content;
        $debug['forceKeywords'] = $forceKeywords;

        if ($assistant) {
            if ($assistant_id) {

                $thread = $this->get_thread();
                $thread_id = $thread['id'];
                $debug['thread_id'] = $thread_id;

                $api_endpoint = $this->endpoint_create_thread . "/" . $thread_id . "/messages";
                $data = [
                    'role' => 'user',
                    'content' => $consideration . "
                    " . $content
                ];
                $debug['request_data'] = $data;
                $thread_response = $this->curl($api_endpoint, $data);
                $debug['thread_requests'] = [];

                $threadId = $thread_response['thread_id'] ?? false;
                if ($threadId) {
                    $run_data = $this->create_run($thread_id, $assistant_id, $consideration);
                    $run_id = $run_data['id'] ?? false;
                    $status = $run_data['status'] ?? false;

                    $counter = 0;

                    $debug['thread_requests'][0] = $run_data;
                    while ($counter < 10 && $status !== 'completed') {
                        sleep(5);
                        $counter++;
                        $refreshed_run_data = $this->refresh_run($thread_id, $run_id);
                        $status = $refreshed_run_data['status'];
                        $debug['thread_requests'][$counter] = $refreshed_run_data;
                    }

                    if ($status === 'completed') {
                        $thread_list = $this->get_thread_list($thread_id);
                        $debug['thread_requests'][$counter + 1] = $thread_list;
                        $message = $this->get_last_assistant_resposne($thread_list);
                    }

                    return ['debug' => $debug, 'message' => $message];
                }
                return [ 'debug' => $debug, 'message' => 'No thread id', 'thread_response' => $thread_response];
            }
            return ['debug' => $debug, 'message' => 'No assistant id', 'assistant' => $assistant];
        }

        return ['debug' => $debug, 'message' => 'No assistant', 'assistant' => $assistant];
    }

    private function create_run($thread_id, $assistant_id, $consideration)
    {

        $url = "https://api.openai.com/v1/threads/$thread_id/runs";
        return $this->curl($url, [
            "assistant_id" => $assistant_id,
            "instructions" => $this->instructions_for_run . " " . $consideration
        ], 'POST');

    }

    private function refresh_run(mixed $thread_id, mixed $run_id)
    {
        $url = "https://api.openai.com/v1/threads/$thread_id/runs/$run_id";
        return $this->curl($url);
    }

    private function get_thread_list(mixed $thread_id)
    {
        $url = "https://api.openai.com/v1/threads/$thread_id/messages";
        return $this->curl($url);
    }

    private function get_last_assistant_resposne(bool|array $thread_list)
    {
        $data = $thread_list['data'] ?? [];
        foreach ($data as $resonse) {
            if ($resonse['role'] === 'assistant') {
                return $resonse['content'][0]['text']['value'];
            }
        }
    }

    private function delete_thread($thread_id)
    {
        $url = "https://api.openai.com/v1/threads/$thread_id";
        $this->curl($url, null, 'DELETE');
    }
}
