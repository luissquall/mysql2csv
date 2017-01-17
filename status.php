<?php
/**
 * # status.php
 * 
 * ## Usage
 * 
 * ```
 * curl http://host/mysql2csv/status.php
 * ```
 * 
 * ## Requirements
 * 
 * 1. `at`
 * ```
 * # On OSX
 * sudo launchctl load -w /System/Library/LaunchDaemons/com.apple.atrun.plist
 * ``` 
 * 2. `files` dir must be writable
 */
$job = '';
$file = '';
if (!empty($_SERVER['QUERY_STRING'])) {
	parse_str($_SERVER['QUERY_STRING'], $query);
	if (!empty($query['job'])) {
		$job = $query['job'];
	}
	if (!empty($query['file'])) {
		$file = $query['file'];
	}
}

$cmd = '/usr/bin/at -l 2>&1';
exec($cmd, $output, $error);
$output = implode("\n", $output);

header('Content-Type: text/html; charset=utf-8');
?>
<pre>
<?php
echo "$cmd\n";
echo "$output\n";

if (!$error) {
	// 45	Mon Jan 16 16:26:00 2017
	if (preg_match(sprintf('/%s\s+/', $job), $output)) {
		$status = 'running';
	} else {
		$status = '-';

		if (file_exists($file)) {
			if (copy($file, sprintf('%s/files/%s', __DIR__, basename($file)))) {
				$status = 'copied';
			}
		}
	}			
	printf("Status: %s\n", $status);
} else {
	echo 'Error';
}
?>
</pre>