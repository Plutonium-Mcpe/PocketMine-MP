<?php

declare(strict_types=1);

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\VersionInfo;

$repo_root = dirname(__DIR__, 2);
require $repo_root . "/vendor/autoload.php";

if (count($argv) !== 5) {
	fwrite(STDERR, "Required arguments: phar path, version, build number, webhook URL\n");
	exit(1);
}

[, $file_path, $version, $build_number, $webhook_url] = $argv;

$composer_json = json_decode(file_get_contents($repo_root . "/composer.json"), true);
$php_version = $composer_json["require"]["php"] ?? "unknown";

$mc_version = ProtocolInfo::MINECRAFT_VERSION_NETWORK;
$mc_protocol = ProtocolInfo::CURRENT_PROTOCOL;
$channel = VersionInfo::BUILD_CHANNEL;

$repo = getenv("GITHUB_REPOSITORY") ?: "Plutonium-Mcpe/PocketMine-MP";
$git_sha = getenv("GITHUB_SHA") ?: "";
$short_sha = $git_sha !== "" ? substr($git_sha, 0, 7) : "unknown";

$release_url = "https://github.com/$repo/releases/tag/$version";

$version_parts = explode(".", $version);
$changelog_file = ($version_parts[0] ?? "") . "." . ($version_parts[1] ?? "") . ".md";
$changelog_anchor = str_replace(".", "", $version);
$changelog_url = "https://github.com/$repo/blob/$version/changelogs/$changelog_file#$changelog_anchor";

$color = $channel === "stable" ? 0x57ab5a : 0xc69026;

$username = "Plutonium";
$avatar_url = "https://images-ext-1.discordapp.net/external/qDGaGMuHy4r7KEwm9geWmzWZadTtjyeqX_--WiRDwlY/https/cdn.discordapp.com/avatars/926159227851014264/47d9fa45edd4eed2162543a49a60c275.webp?format=webp";

$embed = [
	"title" => "Nouvelle version de PocketMine-MP — v$version",
	"url" => $release_url,
	"description" => <<<DESCRIPTION
Les serveurs seront mis à jour une fois que tous les plugins seront compatibles avec cette version.

*Vous trouverez le `.phar` ci-joint.*

[Consulter le changelog]($changelog_url) • [Voir la release sur GitHub]($release_url)
DESCRIPTION,
	"color" => $color,
	"fields" => [
		[
			"name" => "PocketMine-MP",
			"value" => "`v$version`",
			"inline" => true
		],
		[
			"name" => "Build",
			"value" => "`#$build_number`",
			"inline" => true
		],
		[
			"name" => "Channel",
			"value" => "`$channel`",
			"inline" => true
		],
		[
			"name" => "Minecraft Bedrock",
			"value" => "`$mc_version` (protocole `$mc_protocol`)",
			"inline" => true
		],
		[
			"name" => "PHP",
			"value" => "`$php_version`",
			"inline" => true
		],
		[
			"name" => "Commit",
			"value" => "`$short_sha`",
			"inline" => true
		]
	],
	"footer" => [
		"text" => "PocketMine-MP • v$version"
	],
	"timestamp" => date("c")
];

/**
 * @param array<string, mixed>|string $post_fields When string, sent as raw body with application/json.
 *                                                 When array, sent as multipart (curl sets Content-Type with boundary).
 */
function send_webhook(string $webhook_url, array|string $post_fields, string $step) : void {
	$ch = curl_init($webhook_url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
	if (is_string($post_fields)) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
	}
	// For multipart (array with CURLFile), let curl generate the boundary header itself.

	$response = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_error = curl_error($ch);
	curl_close($ch);

	if ($response === false) {
		fwrite(STDERR, "[$step] curl error: $curl_error\n");
		exit(1);
	}

	if ($http_code < 200 || $http_code >= 300) {
		fwrite(STDERR, "[$step] Discord returned HTTP $http_code\n");
		fwrite(STDERR, "body: $response\n");
		exit(1);
	}

	echo "[$step] ok\n";
}

// 1. Embed seul (pas de fichier → JSON brut)
send_webhook($webhook_url, json_encode([
	"username" => $username,
	"avatar_url" => $avatar_url,
	"embeds" => [$embed]
]), "embed");

// 2. Fichier seul (message séparé, apparaît en-dessous) — multipart, curl s'occupe du Content-Type
send_webhook($webhook_url, [
	"payload_json" => json_encode([
		"username" => $username,
		"avatar_url" => $avatar_url
	]),
	"file" => new CURLFile($file_path, "application/octet-stream", basename($file_path))
], "file");
