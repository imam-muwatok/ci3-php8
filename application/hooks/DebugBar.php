<?php
defined('BASEPATH') OR exit('No direct script access allowed');

#[\AllowDynamicProperties]
class DebugBar {

    public function inject_debug_bar() {
        $CI =& get_instance();
        $output = $CI->output->get_output();

        // Jangan jalankan jika request AJAX atau output bukan HTML (misal: JSON atau file)
        if ($CI->input->is_ajax_request() || strpos($output, '</html>') === FALSE) {
            echo $output;
            return;
        }

        // Kumpulkan Data
        $time = $CI->benchmark->elapsed_time('total_execution_time_start', 'total_execution_time_end');
        
        $mem_usage = memory_get_usage();
        $mem_peak = memory_get_peak_usage();
        $mem_limit = $this->_return_bytes(ini_get('memory_limit'));
        
        $memory = round($mem_usage / 1024 / 1024, 2) . ' MB';
        $memory_peak = round($mem_peak / 1024 / 1024, 2) . ' MB';
        $queries = (isset($CI->db) && isset($CI->db->queries)) ? $CI->db->queries : [];
        $query_count = count($queries);
        $session_data = $CI->session->userdata();
        $php_version = PHP_VERSION;
        $ci_version = CI_VERSION;
        $included_files = get_included_files();

        // Ambil data log hari ini
        $config =& get_config();
        $log_path = ($config['log_path'] !== '') ? $config['log_path'] : APPPATH . 'logs/';
        $log_file = $log_path . 'log-' . date('Y-m-d') . '.php';
        $logs = [];
        if (file_exists($log_file)) {
            $log_content = explode("\n", file_get_contents($log_file));
            foreach ($log_content as $line) {
                if (strpos($line, '<?php') !== false || trim($line) === '') continue;
                $logs[] = $line;
            }
        }
        $logs = array_reverse($logs); // Tampilkan log terbaru di atas

        // Safely get controller and method, as router might not be fully initialized or available
        $controller = 'N/A';
        $method = 'N/A';
        if (isset($CI->router) && is_object($CI->router) && method_exists($CI->router, 'fetch_class')) {
            $controller = $CI->router->fetch_class();
            if (method_exists($CI->router, 'fetch_method')) {
                $method = $CI->router->fetch_method();
            }
        }


        // Generate HTML Debug Bar
        $html = $this->_generate_html($time, $memory, $memory_peak, $mem_usage, $mem_limit, $query_count, $queries, $session_data, $logs, $included_files, $php_version, $ci_version, $controller, $method);

        // Sisipkan sebelum tag </body>
        $output = str_replace('</body>', $html . '</body>', $output);
        
        echo $output;
    }

    private function _generate_html($time, $memory, $memory_peak, $mem_usage_raw, $mem_limit_raw, $q_count, $queries, $session_data, $logs, $included_files, $php_v, $ci_v, $ctrl, $method) {
        $CI =& get_instance();
        $style = "
            <style>
                #ci-debug-bar { position: fixed; bottom: 0; left: 0; width: 100%; background: #202326; color: #f8f9fa; z-index: 99999; font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 12px; border-top: 2px solid #343a40; }
                .db-container { display: flex; align-items: center; padding: 5px 15px; }
                .db-item { margin-right: 20px; display: flex; align-items: center; cursor: pointer; color: #ced4da; }
                .db-item:hover { color: #007bff; }
                .db-label { color: #6c757d; margin-right: 5px; text-transform: uppercase; font-weight: bold; }
                .db-val { color: #fff; }
                .db-details-panel { display: none; background: #2b3035; max-height: 300px; overflow-y: auto; padding: 15px; border-top: 1px solid #444; width: 100%; box-sizing: border-box; }
                .query-list { list-style: none; padding: 0; margin: 0; }
                .query-list li { margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #3d444b; word-break: break-all; font-family: monospace; color: #e9ecef; }
                .query-time { color: #ffc107; font-size: 10px; float: right; }
                .log-list { list-style: none; padding: 0; margin: 0; font-family: monospace; font-size: 11px; }
                .log-list li { padding: 3px 0; border-bottom: 1px solid #3d444b; color: #adb5bd; }
                .log-error { color: #ff4560 !important; }
                .db-badge { background: #007bff; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 5px; color: #fff; }
            </style>
        ";

        $query_html = "";
        if (!empty($queries)) {
            foreach ($queries as $key => $q) {
                $q_time = (isset($CI->db->query_times[$key])) ? round($CI->db->query_times[$key], 4) . 's' : '';
                $query_html .= "<li><span class='query-time'>$q_time</span>" . htmlspecialchars($q) . "</li>";
            }
        } else {
            $query_html = "<li>No queries recorded.</li>";
        }

        // Hitung persentase memori untuk grafik
        $mem_percent = ($mem_limit_raw > 0) ? round(($mem_usage_raw / $mem_limit_raw) * 100, 2) : 0;
        if ($mem_limit_raw == -1) $mem_percent = 0; // Handle unlimited
        
        $mem_limit_display = ($mem_limit_raw == -1) ? 'Unlimited' : round($mem_limit_raw / 1024 / 1024, 2) . ' MB';

        $session_html = "<pre style='color: #e9ecef; font-family: monospace; white-space: pre-wrap; word-break: break-all;'>" . htmlspecialchars(json_encode($session_data, JSON_PRETTY_PRINT)) . "</pre>";
        $session_count = count($session_data);

        $files_html = "<ul class='query-list'>";
        foreach ($included_files as $file) {
            $files_html .= "<li>" . htmlspecialchars($file) . "</li>";
        }
        $files_html .= "</ul>";
        $files_count = count($included_files);

        $log_html = "<ul class='log-list'>";
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $is_error = strpos(strtoupper($log), 'ERROR') !== false ? 'log-error' : '';
                $log_html .= "<li class='$is_error'>" . htmlspecialchars($log) . "</li>";
            }
        } else {
            $log_html .= "<li>No logs found for today.</li>";
        }
        $log_html .= "</ul>";


        $bar = "
            <div id='ci-debug-bar'>
                <div id='db-query-details' class='db-details-panel'>
                    <h6 style='color:#17a2b8; margin-bottom:10px;'>Database Queries ($q_count)</h6>
                    <ul class='query-list'>$query_html</ul>
                </div>
                <div id='db-session-details' class='db-details-panel'>
                    <h6 style='color:#17a2b8; margin-bottom:10px;'>Session Data ($session_count)</h6>
                    $session_html
                </div>
                <div id='db-files-details' class='db-details-panel'>
                    <h6 style='color:#17a2b8; margin-bottom:10px;'>Included Files ($files_count)</h6>
                    $files_html
                </div>
                <div id='db-memory-details' class='db-details-panel'>
                    <div style='display: flex; align-items: center; justify-content: space-around;'>
                        <div id='mem-usage-chart' style='min-height: 150px;'></div>
                        <div style='color: #adb5bd;'>
                            <h6 style='color:#17a2b8;'>Memory Details</h6>
                            <p class='mb-1'>Current Usage: <span class='text-white'>$memory</span></p>
                            <p class='mb-1'>Peak Usage: <span class='text-white'>$memory_peak</span></p>
                            <p class='mb-0'>PHP Limit: <span class='text-white'>$mem_limit_display</span></p>
                        </div>
                    </div>
                </div>
                <div id='db-log-details' class='db-details-panel'>
                    <h6 style='color:#ff4560; margin-bottom:10px;'>CI Logs (Today)</h6>
                    $log_html
                </div>
                <div class='db-container'>
                    <div class='db-item' onclick='toggleQueries()'>
                        <span class='db-label'>Queries:</span>
                        <span class='db-val'>$q_count</span>
                        <span class='db-badge'>SQL</span>
                    </div>
                    <div class='db-item'>
                        <span class='db-label'>Time:</span>
                        <span class='db-val'>{$time}s</span>
                    </div>
                    <div class='db-item' onclick='toggleMemory()'>
                        <span class='db-label'>Mem:</span>
                        <span class='db-val'>$memory</span>
                        <span class='db-badge' style='background:#6f42c1'>INFO</span>
                    </div>
                    <div class='db-item' onclick='toggleLogs()'>
                        <span class='db-label'>Logs:</span>
                        <span class='db-val'>".count($logs)."</span>
                        <span class='db-badge' style='background:#dc3545'>LOG</span>
                    </div>
                    <div class='db-item' onclick='toggleFiles()'>
                        <span class='db-label'>Files:</span>
                        <span class='db-val'>$files_count</span>
                        <span class='db-badge' style='background:#28a745'>FILES</span>
                    </div>
                    <div class='db-item' onclick='toggleSession()'>
                        <span class='db-label'>Session:</span>
                        <span class='db-val'>$session_count</span>
                        <span class='db-badge'>DATA</span>
                    </div>
                    <div class='db-item' title='Controller/Method'>
                        <span class='db-label'>Route:</span>
                        <span class='db-val'>$ctrl / $method</span>
                    </div>
                    <div style='flex-grow: 1'></div>
                    <div class='db-item'>
                        <span class='db-label'>PHP:</span>
                        <span class='db-val'>$php_v</span>
                    </div>
                    <div class='db-item'>
                        <span class='db-label'>CI:</span>
                        <span class='db-val'>$ci_v</span>
                    </div>
                </div>
            </div>
            <script>
                var memChartRendered = false;
                function togglePanel(id) {
                    var panels = ['db-query-details', 'db-session-details', 'db-files-details', 'db-memory-details', 'db-log-details'];
                    panels.forEach(function(p) {
                        var el = document.getElementById(p);
                        if (p === id) {
                            el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
                        } else {
                            el.style.display = 'none';
                        }
                    });
                }
                function toggleQueries() { togglePanel('db-query-details'); }
                function toggleMemory() {
                    togglePanel('db-memory-details');
                    var el = document.getElementById('db-memory-details');
                    if (el.style.display === 'none' || el.style.display === '') {
                        renderMemChart();
                    }
                }
                function renderMemChart() {
                    if (memChartRendered) return;
                    if (typeof ApexCharts !== 'undefined') {
                        var options = {
                            series: [$mem_percent],
                            chart: { height: 160, type: 'radialBar', sparkline: { enabled: true } },
                            plotOptions: {
                                radialBar: {
                                    hollow: { size: '60%' },
                                    dataLabels: {
                                        name: { show: false },
                                        value: {
                                            offsetY: 5,
                                            fontSize: '14px',
                                            color: '#fff',
                                            formatter: function(val) { return val + '%' }
                                        }
                                    }
                                }
                            },
                            colors: ['#6f42c1'],
                            stroke: { lineCap: 'round' }
                        };
                        var chart = new ApexCharts(document.querySelector(\"#mem-usage-chart\"), options);
                        chart.render();
                        memChartRendered = true;
                    }
                }
                function toggleLogs() { togglePanel('db-log-details'); }
                function toggleFiles() { togglePanel('db-files-details'); }
                function toggleSession() { togglePanel('db-session-details'); }
            </script>
        ";

        return $style . $bar;
    }

    private function _return_bytes($val) {
        $val = trim($val);
        if (empty($val) || $val == '-1') return -1;
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024 * 1024 * 1024; break;
            case 'm': $val *= 1024 * 1024; break;
            case 'k': $val *= 1024; break;
        }
        return $val;
    }
}