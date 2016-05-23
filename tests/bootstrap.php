<?php

$tmpdir = __DIR__.'/tmp';
if (file_exists($tmpdir)) {

    function rmtree($dir) {
        if (false === file_exists($dir)) {
            return false;
        }

        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $entry) {
            $basename = $entry->getBasename();
            if ($entry->isDot() || '.' === $basename[0]) {
                continue;
            }
            if ($entry->isDir()) {
                rmtree($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($dir);

        return true;
    }

    rmtree($tmpdir);
}

mkdir($tmpdir);
