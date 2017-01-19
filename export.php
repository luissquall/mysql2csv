<?php
/**
 * # export.php
 * 
 * ## Usage
 * 
 * ```
 * curl http://host/mysql2csv/export.php
 * ```
 * 
 * ## Requirements
 * 
 * 1. `at`
 * ```
 * # On OSX
 * sudo launchctl load -w /System/Library/LaunchDaemons/com.apple.atrun.plist
 *
 * echo 'echo 1 > at-1' | at now
 * # Should print 1 after 15 seconds
 * sleep 15 && cat at-1 && rm at-1
 * ``` 
 * 
 * 2. `mysql`
 * 
 * 3. db user with FILE permissions
 * ```
 * echo "GRANT FILE ON *.* TO '$user'@'localhost';" | mysql -u root -p
 * ```
 * 
 * 4. Make a copy of config.default.php & edit the new file
 * ```
 * cp config.default.php config.php
 * vim config.php
 * ```
 * 5. Folder $config['folder'] should be writable by mysql user
 * 
 * 6. Remove user www-data from `/etc/at.deny
 * 
 * ## Debugging
 * 
 * ```
 * ls -ltha $folder/*
 * 
 * sudo at -l
 * ```
 */
include 'config.php';

$job = '';
$filename = sprintf(
	$config['filename'],
	hash('sha256', $config['query'] . time())
);
$file = sprintf('%s/%s', $config['folder'], $filename);
$cmds = array(
	'mysql' => '/usr/bin/mysql',
	'sed' => "/bin/sed -i '1s/^/\\xef\\xbb\\xbf{{ headers }}\\n/'"
);
if (PHP_OS == 'Darwin') {
	$cmds = array(
		'mysql' => '/usr/local/mysql/bin/mysql',
		'sed' => "/usr/bin/sed -i '' -e '1s/^/'\$'\\xEF\\xBB\\xBF''{{ headers }}\\'\$'\\n''/'"
	);
}
if (!empty($config['bins']['mysql'])) {
	$cmds['mysql'] = $config['bins']['mysql'];
}

$headers = str_replace("'", "'\"'\"'", $config['headers']);
$cmds['sed'] = str_replace('{{ headers }}', $headers, $cmds['sed']);
$match = array(
	'{{ mysql_cmd }}' => $cmds['mysql'],
	'{{ sed_cmd }}' => $cmds['sed'],
	'{{ user }}' => $config['user'],
	'{{ password }}' => $config['password'],
	'{{ database }}' => $config['database'],
	'{{ query }}' => $config['query'],
	'{{ file }}' => $file,
	'{{ error_log }}' => empty($config['error_log']) ? __DIR__ . '/error.log' : $config['error_log']
);
$cmd = str_replace(
	array_keys($match),
	array_values($match),
"/usr/bin/at now << 'EOF' 2>&1
{{ mysql_cmd }} -u {{ user }} -p'{{ password }}' {{ database }} 2>> '{{ error_log }}' << 'EOF2' && {{ sed_cmd }} '{{ file }}'
{{ query }}
INTO OUTFILE '{{ file }}'
FIELDS TERMINATED BY ','
ENCLOSED BY '\"'
LINES TERMINATED BY '\\n';
EOF2
EOF
");
$output = shell_exec($cmd);

header('Content-Type: text/html; charset=utf-8');
?>
<pre>
<?php 
echo "$cmd\n";

if ($output !== null) {
	// job 38 at Mon Jan 16 15:58:02 2017
	preg_match('/job\s+(\d+)\s+/', $output, $matches);
	$job = empty($matches) ? '' : $matches[1];
	$url = sprintf(
		'%s?job=%s&file=%s',
		$config['status_service'],
		urlencode($job),
		urlencode($file)
	);

	printf("Job: %s\n", $job);
	printf("File: %s\n", $file);
	printf("Status URL: <a href='%s' target='_blank'>%s</a>", $url, $url);
} else {
	echo 'Error';
}
?>
</pre>
