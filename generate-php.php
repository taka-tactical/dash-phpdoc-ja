<?php
/**
dash-phpdoc-ja

Copyright (c) 2017 T.Takamatsu <takamatsu@tactical.jp>

This software is released under the MIT License.
http://opensource.org/licenses/mit-license.php
*/


//----------------------------------------
// Config
//----------------------------------------

// set your language (en/ja/ru/ro/es/tr/fr/de/zh/pt_BR)
$cfg_lang = 'ja';
$cfg_ver  = '8.0';

// set your chm-extract command
// ** must be 'sprintf() format'
// *** arg1: target chm file, arg2: extract dir

// Windows
$cfg_chm  = 'hh -decompile %2$s %1$s';
// Mac, Linux
//$cfg_chm  = 'extract_chmLib %1$s %2$s';

// set true, if you have font trouble with google open sans (e.g. Zeal on windows)
$cfg_nosans = true;


//----------------------------------------
// Consts
//----------------------------------------

// File path
$c_origd = __DIR__  . '/en_orig_docset';
$c_mychm = __DIR__  . '/my_lang_chmdec';
$c_cbase = __DIR__  . '/PHP.docset/Contents';
$c_rbase = $c_cbase . '/Resources';
$c_dbase = $c_rbase . '/Documents';

$c_guide = 'Guide';

//
// PHP docset URL
// @link https://github.com/Kapeli/feeds/blob/master/PHP.xml
// ** select download url from above xml.
//
$c_url_doc = 'http://tokyo.kapeli.com/feeds/PHP.tgz';
//
// PHP manual URL
// @link http://php.net/download-docs.php
// ** chm (manual or enhanced) only!
//
$c_url_chm = "http://jp2.php.net/get/php_enhanced_{$cfg_lang}.chm/from/this/mirror";


//----------------------------------------
//
// Main process
//
//----------------------------------------

echo "\nStart build PHP {$cfg_ver} docset ...\n";
echo "\nDownload original docset (en) and 'CHM' help file ...\n\n";

$target_chm   = basename($c_url_chm);
$target_doc   = basename($c_url_doc);
$clean_on_err = false;

try {
	// get php manual (en) docset
	remove_dir("{$c_rbase}");

	if (
		!mkdir("{$c_dbase}/", 0777, true) ||
		!mkdir("{$c_origd}/", 0777, true)
	) {
		do_exception(__LINE__);
	}

	exec_ex("wget {$c_url_doc}");
	exec_ex("wget --trust-server-names {$c_url_chm}");
	$match = [];

	if (preg_match('#.+/get/(php_(manual|enhanced)_ja\.chm)/from/.+#i', $c_url_chm, $match)) {
		$target_chm = $match[1];
		echo "\nDetect CHM file '{$target_chm}'. set as target ...\n";
	}
	else {
		$target_chm = basename($c_url_chm);
		echo "\nDetect CHM failure, fallback to '{$target_chm}'. set as target ...\n";
	}

	echo "\nReplace docset files for your language ...\n\n";

	// extract
	exec_ex("tar xzf {$target_doc} -C {$c_origd} --strip-components 1");
	sleep(5);

	$base_dir = "{$c_origd}/Contents/Resources/Documents/www.php.net/manual/en";

	// replace html
	// ** note: Do not use 'rm' command.
	// **       It will cause device busy or 'Argument list too long' error.
	foreach ([
		'array*', 'book.*', 'class.*', 'function.*', 'imagick*',
		'intro.*', 'mongo*', 'mysql*', 'ref.*', 'yaf-*',
	] as $val) {
		echo "Removing original {$val} ...\n";

		foreach (glob("{$base_dir}/{$val}") as $file) {
			if (!unlink($file)) {
				do_exception(__LINE__);
			}
		}
		sleep(2);
	}

	echo "Removing original manual/en ...\n";
	remove_dir($base_dir);
	exec_ex(sprintf($cfg_chm, $target_chm, $c_mychm));
	sleep(1);

	if (!unlink("{$c_mychm}/res/style.css")) {
		do_exception(__LINE__);
	}

	// copy database
	if (
		!copy("{$c_origd}/Contents/Resources/docSet.dsidx", "{$c_rbase}/docSet.dsidx") ||
		!copy("{$c_origd}/Contents/Resources/docSet.dsidx", "{$c_rbase}/docSet.dsidx.orig")
	) {
		do_exception(__LINE__);
	}

	// copy & replace documents
	if (!rename("{$c_origd}/Contents/Resources/Documents/php.net", "{$c_dbase}/php.net")) {
		do_exception(__LINE__);
	}
	if (!rename("{$c_origd}/Contents/Resources/Documents/www.php.net", "{$c_dbase}/www.php.net")) {
		do_exception(__LINE__);
	}
	if (!rename("{$c_mychm}/res", "{$c_dbase}/www.php.net/manual/en")) {
		do_exception(__LINE__);
	}

	if (!copy(
		__DIR__ . sprintf('/%s', $cfg_nosans ? 'style-nosans.css' : 'style.css'),
		"{$c_dbase}/www.php.net/manual/en/style.css"
	)) {
		do_exception(__LINE__);
	}
}
catch (Exception $e) {
	$clean_on_err = true;
	throw new Exception("\nPHP docset build failed.\nFix error and try again.\n\n", -1, $e);
}
finally {
	// clean up
	if ($clean_on_err) {
		@unlink(__DIR__ . "/{$target_doc}");
		@unlink(__DIR__ . "/{$target_chm}");

		try { remove_dir("{$c_origd}"); } catch (Exception $e) { }
		try { remove_dir("{$c_mychm}"); } catch (Exception $e) { }
	} else {
		unlink(__DIR__ . "/{$target_doc}");
		unlink(__DIR__ . "/{$target_chm}");

		remove_dir("{$c_origd}");
		remove_dir("{$c_mychm}");
	}
}

// gen Info.plist
file_put_contents("{$c_cbase}/Info.plist", <<<ENDE
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>CFBundleIdentifier</key>
	<string>phpdoc-{$cfg_lang}</string>
	<key>CFBundleName</key>
	<string>PHP {$cfg_ver}-{$cfg_lang}</string>
	<key>DocSetPlatformFamily</key>
	<string>php</string>
	<key>dashIndexFilePath</key>
	<string>www.php.net/manual/en/index.html</string>
	<key>DashDocSetFamily</key>
	<string>dashtoc</string>
	<key>isDashDocset</key>
	<true/>
</dict>
</plist>
ENDE
);
copy(__DIR__ . '/icon.png',    "{$c_cbase}/../icon.png");
copy(__DIR__ . '/icon@2x.png', "{$c_cbase}/../icon@2x.png");


// update db (add japanese indexes)
echo "\nAdd search indexes from Title ...\n\n";

$db = new PDO("sqlite:{$c_rbase}/docSet.dsidx");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$res = $db->query("PRAGMA table_info('searchIndex')");
$val = false;

foreach ($res as $row) {
	if ($row['name'] == 'lang') {
		$val = true;
		break;
	}
}

if (!$val) {
	$db->exec("ALTER TABLE searchIndex ADD COLUMN lang TEXT DEFAULT 'en'");
	$db->exec("UPDATE searchIndex SET lang = 'en'");
}

$stmt = $db->prepare('SELECT * FROM searchIndex WHERE type = ?');
$stmt->execute([$c_guide]);

$res  = $stmt->fetchAll();
$dom  = new DomDocument();
$list = [];

foreach ($res as $row) {
	if (!$html = file_get_contents("{$c_dbase}/{$row['path']}")) {
		continue;
	}
	@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'SJIS-win'));

	if (!$t = $dom->getElementsByTagName('title')->item(0)) {
		continue;
	}

	$list[] = [$t->nodeValue, $c_guide, $row['path'], 'ja'];
}

$stmt = $db->prepare('INSERT OR IGNORE INTO searchIndex(name, type, path, lang) VALUES (?, ?, ?, ?)');

foreach ($list as $val) {
	$stmt->execute($val);
}

echo "\nPHP {$cfg_ver} docset updated !\n\n";


//----------------------------------------
// Helper functions
//----------------------------------------

/**
 * Throw Exception
 *
 * @param mixed $line
 * @param integer $code
 * @throws Exception
 */
function do_exception($line, $code = -1) {
	throw new Exception("Error at line: {$line}", $code);
}

/**
 * Exec with exception logic
 *
 * @param string $cmd
 * @return boolean
 */
function exec_ex($cmd) {
	if (($cmd = strval($cmd)) === '') {
		do_exception(__LINE__);
	}

	$out = [];
	$ret = 0;

	echo "Exec: {$cmd}\n";
	exec($cmd, $out, $ret);

	$log = implode("\n", $out);
	echo "Exec status: {$ret}\nExec output: {$log}\n\n";

	if ($ret) {
		do_exception(__LINE__, $ret);
	}

	return true;
}

/**
 * Remove directory recursively
 *
 * @param string $dir
 */
function remove_dir($dir) {
	if ($dir === "" || !is_dir($dir)) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iterator as $item) {
		if ($item->isDir()) {
			rmdir($item->getPathname());
		} else {
			unlink($item->getPathname());
		}
	}

	if (!rmdir($dir)) {
		do_exception(__LINE__);
	}
}


