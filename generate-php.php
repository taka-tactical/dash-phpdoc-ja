<?php
/**
 * dash-phpdoc-ja
 *
 * Copyright (c) 2017 T.Takamatsu <takamatsu@tactical.jp>
 *
 * This software is released under the MIT License.
 * http://opensource.org/licenses/mit-license.php
 */


//----------------------------------------
// Config
//----------------------------------------

// acceptable lang
$cfg_lang_list = ['ja', 'en', 'es', 'tr', 'fr', 'de', 'zh', 'pt_BR', 'ru'];
$cfg_lang = $cfg_lang_list[0];

// fallback version number
$cfg_ver = '8.0';

// set your chm-extract command
// ** must be 'sprintf() format'
// *** arg1: target chm file, arg2: extract dir
$cfg_chm = match (php_uname('s')) {
    'Darwin' => 'extract_chmLib %1$s %2$s',     // MacOS
    default => 'hh -decompile %2$s %1$s',      // Fallback to Windows
};

// set true, if you have font trouble with google open sans (e.g. Zeal on windows)
$cfg_nosans = true;


//----------------------------------------
// Consts
//----------------------------------------

// File path
$c_orign = 'en_orig_docset';
$c_origd = __DIR__ . '/' . $c_orign;
$c_mychm = __DIR__ . '/my_lang_chmdec';
$c_cbase = __DIR__ . '/PHP.docset/Contents';
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
$c_url_tpl = "https://www.php.net/distributions/manual/php_enhanced_%s.chm";
$c_url_chm = sprintf($c_url_tpl, $cfg_lang);


//----------------------------------------
//
// Main process
//
//----------------------------------------

$opts = getopt('h', ['lang::']);

if (isset($opts['h'])) {
    echo print_usage();
    exit;
} else if (isset($opts['lang'])) {
    if (!validate_lang($opts['lang'])) {
        echo "\nInvalid or not supported launguage.\nFix error and try again.\n\n";
        exit;
    }

    $cfg_lang  = $opts['lang'];
    $c_url_chm = sprintf($c_url_tpl, $cfg_lang);
}

echo "\nStart build PHP docset [lang = {$cfg_lang}] ...\n";
echo "\nDownload original docset (en) and 'CHM' help file ...\n\n";

$target_chm = basename($c_url_chm);
$target_doc = basename($c_url_doc);

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

    echo "\nReplace docset files for your language ...\n\n";

    // extract
    exec_ex("tar xzf {$target_doc} -C ./{$c_orign} --strip-components 1");
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

    // get latest version number
    echo "\nDetect latest version number ... ";
    $migrate_nums = [];

    foreach (glob("{$c_mychm}/res/migration[0-9]*.html") as $file) {
        $name = str_replace('migration', '', basename($file, '.html'));

        if (is_numeric($name)) {
            $migrate_nums[] = $name;
        }
    }
    $latest_ver = max($migrate_nums);
    $detect_ver = '';

    if ($migrateLast = file_get_contents("{$c_mychm}/res/migration{$latest_ver}.html")) {
        $match = [];

        if (preg_match('#<title>.*PHP ([\d.]+)\.x .+ PHP ([\d.]+)\.x.*</title>#i', $migrateLast, $match)) {
            $detect_ver = $match[2];
        }
    }

    if ($detect_ver !== '') {
        $cfg_ver = $detect_ver;
    } else {
        $detect_ver = "{$cfg_ver} (failed, set fallback number)";
    }
    echo "{$detect_ver}\n\n";

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
} catch (Exception $e) {
    throw new RuntimeException("\nPHP docset build failed.\nFix error and try again.\n\n", -1, $e);
} finally {
    // clean up
    @unlink(__DIR__ . "/{$target_doc}");
    @unlink(__DIR__ . "/{$target_chm}");

    try {
        remove_dir($c_origd);
    } catch (Exception $e) {}
    try {
        remove_dir($c_mychm);
    } catch (Exception $e) {}
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
copy(__DIR__ . '/icon.png', "{$c_cbase}/../icon.png");
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

$res = $stmt->fetchAll();
$dom = new DomDocument();
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

echo "\nPHP docset updated !\n\n";


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
function do_exception(mixed $line, int $code = -1): void
{
    throw new Exception("Error at line: {$line}", $code);
}

/**
 * Exec with exception logic
 *
 * @param string $cmd
 * @return boolean
 * @throws Exception
 */
function exec_ex(string $cmd): bool
{
    if ($cmd === '') {
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
 * @throws Exception
 */
function remove_dir(string $dir): void
{
    if ($dir === '' || !is_dir($dir)) {
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

/**
 * Print help screen
 *
 * @return string
 */
function print_usage(): string
{
    $name = basename(__FILE__);
    return <<<EOD

Usage:
  {$name} [-h | --lang=lang]

Description:
  Generates a Dash docset for PHP.

Options:
  --lang  specify language of docset (ja/en/es/tr/fr/de/zh/pt_BR/ru).
          if omitted, "ja" is applied.
  -h      display this help and exit


EOD;
}

/**
 * Validate language input
 *
 * @param mixed $lang
 * @return bool
 */
function validate_lang(mixed $lang): bool
{
    global $cfg_lang_list;
    return is_string($lang) && in_array($lang, $cfg_lang_list, true);
}
