<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$root = __DIR__
    . DIRECTORY_SEPARATOR . "Assets"
    . DIRECTORY_SEPARATOR . "Audio"
    . DIRECTORY_SEPARATOR . "Browser";

/*
 * Read the requested relative directory.
 * The root directory is represented by an empty path.
 */
$relative = isset($_GET["path"]) ? $_GET["path"] : "";

$relative = str_replace("\\", "/", $relative);
$parts = explode("/", $relative);
$safeParts = [];

foreach ($parts as $part) {
    if ($part === "" || $part === ".") {
        continue;
    }

    if ($part === "..") {
        http_response_code(400);
        echo json_encode(["error" => "Invalid path"]);
        exit;
    }

    $safeParts[] = $part;
}

$realRoot = realpath($root);

if ($realRoot === false || !is_dir($realRoot)) {
    http_response_code(500);
    echo json_encode([
        "error" => "Audio browser root directory does not exist."
    ]);
    exit;
}

$directory = $realRoot;

foreach ($safeParts as $part) {
    $directory .= DIRECTORY_SEPARATOR . $part;
}

$realDirectory = realpath($directory);

/*
 * Ensure the requested directory exists and remains inside
 * the configured audio browser root.
 */
if (
    $realDirectory === false ||
    !is_dir($realDirectory) ||
    (
        $realDirectory !== $realRoot &&
        strpos(
            $realDirectory,
            $realRoot . DIRECTORY_SEPARATOR
        ) !== 0
    )
) {
    http_response_code(404);
    echo json_encode(["error" => "Folder not found"]);
    exit;
}

$folders = [];
$audioFiles = [];

$entries = scandir($realDirectory);

if ($entries === false) {
    http_response_code(500);
    echo json_encode(["error" => "Could not read folder"]);
    exit;
}

foreach ($entries as $name) {
    if ($name === "." || $name === "..") {
        continue;
    }

    $fullPath = $realDirectory . DIRECTORY_SEPARATOR . $name;

    /* Ignore symbolic links. */
    if (is_link($fullPath)) {
        continue;
    }

    if (is_dir($fullPath)) {
        $folders[] = [
            "name" => $name,
            "type" => "folder"
        ];

        continue;
    }

    if (!is_file($fullPath)) {
        continue;
    }

    $extension = strtolower(
        pathinfo($name, PATHINFO_EXTENSION)
    );

    if ($extension === "wav" || $extension === "mp3") {
        $audioFiles[] = [
            "name" => $name,
            "type" => "audio",
            "extension" => $extension
        ];
    }
}

/* Sort folders and audio files alphabetically. */
usort($folders, function ($a, $b) {
    return strnatcasecmp($a["name"], $b["name"]);
});

usort($audioFiles, function ($a, $b) {
    return strnatcasecmp($a["name"], $b["name"]);
});

echo json_encode([
    "path" => "./assets/audio/browser/" .
        implode("/", $safeParts),
    "folders" => $folders,
    "files" => $audioFiles
], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

?>