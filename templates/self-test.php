<?php
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$nonce    = wp_create_nonce('wp_rest');
$rest_url = rest_url('seo-audit/v1/self-test');
$all_tests = \SeoAudit\SelfTest::get_all_tests();

$groups = [];
foreach ($all_tests as $id => $test) {
    $groups[$test['group']][$id] = $test;
}
?>
<h1>Self Test</h1>
<p>Select tests to run and click <strong>Run Selected Tests</strong>. Each test verifies a specific SEO check against synthetic HTML or the live site.</p>

<form id="yolsa-self-test-form">
    <div id="yolsa-self-test-tests">
        <label style="display:inline-block;margin-bottom:10px;">
            <input type="checkbox" id="yolsa-select-all" checked>
            <strong>Select / deselect all</strong>
        </label>
        <?php foreach ($groups as $group_label => $group_tests): ?>
            <fieldset style="border:1px solid #ccd0d4;padding:10px 15px;margin-bottom:12px;border-radius:4px;">
                <legend style="font-weight:600;padding:0 6px;"><?= esc_html($group_label) ?></legend>
                <?php foreach ($group_tests as $id => $test): ?>
                    <label style="display:block;margin:4px 0;">
                        <input type="checkbox" name="tests[]" value="<?= esc_attr($id) ?>" checked>
                        <?= esc_html($test['label']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
    </div>

    <p>
        <button type="submit" class="button button-primary" id="yolsa-run-tests">Run Selected Tests</button>
        <span id="yolsa-running-indicator" style="display:none;margin-left:10px;">Running&hellip;</span>
    </p>
</form>

<div id="yolsa-self-test-results" style="display:none;margin-top:20px;">
    <h2>Results</h2>
    <table class="yolsa-table widefat striped" style="margin-bottom:10px;">
        <thead>
            <tr>
                <th style="text-align:left;width:40%;">Test</th>
                <th style="width:80px;">Status</th>
                <th style="text-align:left;">Details</th>
            </tr>
        </thead>
        <tbody id="yolsa-self-test-results-body"></tbody>
    </table>
    <p id="yolsa-self-test-summary" style="font-weight:600;font-size:14px;"></p>
</div>

<script>
(function () {
    const nonce   = <?= json_encode($nonce) ?>;
    const restUrl = <?= json_encode($rest_url) ?>;

    const selectAll  = document.getElementById('yolsa-select-all');
    const checkboxes = document.querySelectorAll('#yolsa-self-test-tests input[name="tests[]"]');

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
    });

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            selectAll.checked = Array.from(checkboxes).every(function (c) { return c.checked; });
        });
    });

    document.getElementById('yolsa-self-test-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const selected = Array.from(checkboxes)
            .filter(function (cb) { return cb.checked; })
            .map(function (cb) { return cb.value; });

        if (!selected.length) {
            alert('Please select at least one test.');
            return;
        }

        const runBtn    = document.getElementById('yolsa-run-tests');
        const indicator = document.getElementById('yolsa-running-indicator');
        const resultsDiv  = document.getElementById('yolsa-self-test-results');
        const resultsBody = document.getElementById('yolsa-self-test-results-body');
        const summary     = document.getElementById('yolsa-self-test-summary');

        runBtn.disabled      = true;
        indicator.style.display = 'inline';
        resultsBody.innerHTML   = '';
        summary.textContent     = '';
        resultsDiv.style.display = 'block';

        const query = selected.map(function (t) { return 'tests[]=' + encodeURIComponent(t); }).join('&');

        fetch(restUrl + '?' + query, {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.json();
        })
        .then(function (data) {
            let passed = 0, failed = 0;

            for (const id in data) {
                const item   = data[id];
                const result = item.result;
                const row    = document.createElement('tr');

                const statusStyle = result.pass
                    ? 'color:#1a7e1a;font-weight:bold;'
                    : 'color:#cc0000;font-weight:bold;';
                const statusText = result.pass ? '&#10003; PASS' : '&#10007; FAIL';

                row.innerHTML =
                    '<td style="text-align:left;">' + escHtml(item.label) + '</td>' +
                    '<td style="' + statusStyle + '">' + statusText + '</td>' +
                    '<td style="text-align:left;">' + escHtml(result.message) + '</td>';

                resultsBody.appendChild(row);
                result.pass ? passed++ : failed++;
            }

            const total = passed + failed;
            summary.textContent = 'Passed: ' + passed + ' / ' + total;
            summary.style.color = failed === 0 ? '#1a7e1a' : '#cc0000';
        })
        .catch(function (err) {
            summary.textContent = 'Error: ' + err.message;
            summary.style.color = '#cc0000';
        })
        .finally(function () {
            runBtn.disabled = false;
            indicator.style.display = 'none';
        });
    });

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }
}());
</script>
