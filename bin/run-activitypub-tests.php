<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', ['base:', 'username:', 'insecure', 'quiet']);
$base = rtrim((string) ($options['base'] ?? ''), '/');
$username = (string) ($options['username'] ?? '');
$insecure = array_key_exists('insecure', $options);
$quiet = array_key_exists('quiet', $options);

if ($base === '' || $username === '') {
    fwrite(STDERR, "Usage: php bin/run-activitypub-tests.php --base=https://example.org --username=alice [--insecure] [--quiet]\n");
    exit(2);
}

if (!filter_var($base, FILTER_VALIDATE_URL) || !str_starts_with($base, 'https://')) {
    fwrite(STDERR, "--base must be an HTTPS URL.\n");
    exit(2);
}

$passed = 0;
$failed = 0;
$skipped = 0;

$section = static function (string $name) use ($quiet): void {
    if (!$quiet) {
        echo "\n$name\n";
    }
};

$pass = static function (string $message) use (&$passed, $quiet): void {
    $passed++;

    if (!$quiet) {
        echo "PASS: $message\n";
    }
};

$fail = static function (string $message) use (&$failed): void {
    $failed++;
    echo "FAIL: $message\n";
};

$skip = static function (string $message) use (&$skipped): void {
    $skipped++;
    echo "SKIP: $message\n";
};

$check = static function (bool $condition, string $message) use ($pass, $fail): void {
    $condition ? $pass($message) : $fail($message);
};

/**
 * @param string[] $headers
 * @return array{status: int, headers: array<string, string[]>, body: string, json: mixed}
 */
$request = static function (
    string $url,
    string $method = 'GET',
    string $accept = 'application/activity+json',
    ?string $body = null,
    array $headers = []
) use ($insecure): array {
    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('Could not initialize curl.');
    }

    $curl_options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: ' . $accept,
            'User-Agent: Glommer-ActivityPub-Test/2.0',
        ], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => !$insecure,
        CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
    ];

    if ($body !== null) {
        $curl_options[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($curl, $curl_options);
    $response = curl_exec($curl);

    if (!is_string($response)) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException($url . ': ' . $error);
    }

    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $header_size = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    $header_lines = preg_split('/\r?\n/', trim(substr($response, 0, $header_size))) ?: [];
    $response_headers = [];

    foreach ($header_lines as $line) {
        if (str_starts_with($line, 'HTTP/')) {
            $response_headers = [];
            continue;
        }

        if (!str_contains($line, ':')) {
            continue;
        }

        [$name, $value] = explode(':', $line, 2);
        $response_headers[strtolower(trim($name))][] = trim($value);
    }

    $response_body = substr($response, $header_size);

    return [
        'status' => $status,
        'headers' => $response_headers,
        'body' => $response_body,
        'json' => json_decode($response_body, true),
    ];
};

$hasHeaderValue = static function (array $response, string $name, string $value): bool {
    foreach ($response['headers'][strtolower($name)] ?? [] as $header_value) {
        if (str_contains(strtolower($header_value), strtolower($value))) {
            return true;
        }
    }

    return false;
};

$hasNoCookies = static fn (array $response): bool => !isset($response['headers']['set-cookie']);
$isTimestamp = static fn (mixed $value): bool => is_string($value) && strtotime($value) !== false;

$isHTTPSURL = static function (mixed $url): bool {
    return is_string($url)
        && filter_var($url, FILTER_VALIDATE_URL) !== false
        && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
};

$sameOrigin = static function (string $left, string $right): bool {
    $origin = static function (string $url): string {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme . '://' . strtolower($parts['host']) . ':' . $port;
    };

    return $origin($left) !== '' && $origin($left) === $origin($right);
};

$checkActivityResponse = static function (array $response, string $name) use ($check, $hasHeaderValue, $hasNoCookies): void {
    $check($response['status'] === 200, $name . ' returns 200');
    $check($hasHeaderValue($response, 'Content-Type', 'application/activity+json'), $name . ' uses the ActivityPub media type');
    $check($hasHeaderValue($response, 'Vary', 'Accept'), $name . ' varies on Accept');
    $check($hasNoCookies($response), $name . ' is stateless');
    $check(is_array($response['json']), $name . ' is a JSON object');
    $check(is_array($response['json']) && isset($response['json']['@context']), $name . ' supplies an ActivityStreams context');
};

$actor_url = $base . '/users/' . rawurlencode($username) . '/';
$host = (string) parse_url($base, PHP_URL_HOST);
$port = parse_url($base, PHP_URL_PORT);
$authority = $host . ($port === null ? '' : ':' . $port);
$webfinger_url = $base . '/.well-known/webfinger?resource=' . rawurlencode('acct:' . $username . '@' . $authority);
$unknown = '__activitypub_missing_' . bin2hex(random_bytes(6));

try {
    $section('WebFinger');
    $webfinger = $request($webfinger_url, 'GET', 'application/jrd+json');
    $check($webfinger['status'] === 200, 'WebFinger returns 200');
    $check($hasHeaderValue($webfinger, 'Content-Type', 'application/jrd+json'), 'WebFinger uses the JRD media type');
    $check($hasNoCookies($webfinger), 'WebFinger is stateless');
    $check(($webfinger['json']['subject'] ?? null) === 'acct:' . $username . '@' . $authority, 'WebFinger returns the requested subject');
    $check(in_array($actor_url, $webfinger['json']['aliases'] ?? [], true), 'WebFinger aliases include the canonical actor URL');

    $links = is_array($webfinger['json']['links'] ?? null) ? $webfinger['json']['links'] : [];
    $self_links = array_values(array_filter($links, static fn (mixed $link): bool => is_array($link) && ($link['rel'] ?? null) === 'self'));
    $profile_links = array_values(array_filter($links, static fn (mixed $link): bool => is_array($link) && ($link['rel'] ?? null) === 'http://webfinger.net/rel/profile-page'));
    $check(count($self_links) === 1, 'WebFinger supplies exactly one self link');
    $check(($self_links[0]['href'] ?? null) === $actor_url, 'WebFinger self link names the canonical actor URL');
    $check(($self_links[0]['type'] ?? null) === 'application/activity+json', 'WebFinger self link advertises ActivityPub JSON');
    $check(count($profile_links) === 1 && ($profile_links[0]['href'] ?? null) === $actor_url, 'WebFinger profile link names the canonical profile URL');
    $check(($profile_links[0]['type'] ?? null) === 'text/html', 'WebFinger profile link advertises HTML');

    $case_folded = $request($base . '/.well-known/webfinger?resource=' . rawurlencode('acct:' . $username . '@' . strtoupper($authority)), 'GET', 'application/jrd+json');
    $check($case_folded['status'] === 200, 'WebFinger matches hostnames case-insensitively');

    foreach (['', 'not-an-acct-uri', 'acct:' . $username . '@example.invalid', 'acct:' . $unknown . '@' . $authority] as $index => $resource) {
        $invalid = $request($base . '/.well-known/webfinger?resource=' . rawurlencode($resource), 'GET', 'application/jrd+json');
        $check($invalid['status'] === 404, 'WebFinger rejects invalid or unknown resource #' . ($index + 1));
        $check($hasNoCookies($invalid), 'rejected WebFinger resource #' . ($index + 1) . ' is stateless');
    }

    $webfinger_post = $request($webfinger_url, 'POST', 'application/jrd+json');
    $check($webfinger_post['status'] === 405, 'WebFinger rejects POST');
    $check($hasHeaderValue($webfinger_post, 'Allow', 'GET'), 'WebFinger POST response advertises GET');

    $section('Actor and negotiation');
    $actor = $request($actor_url);
    $checkActivityResponse($actor, 'actor');

    if (!is_array($actor['json'])) {
        throw new RuntimeException('Actor response is not a JSON object.');
    }

    $actor_document = $actor['json'];
    $check(($actor_document['id'] ?? null) === $actor_url, 'actor id is its canonical URL');
    $check(($actor_document['type'] ?? null) === 'Person', 'member actor is a Person');
    $check(($actor_document['preferredUsername'] ?? null) === $username, 'actor preferredUsername matches WebFinger');
    $check(is_string($actor_document['name'] ?? null) && trim($actor_document['name']) !== '', 'actor has a nonempty name');
    $check(($actor_document['url'] ?? null) === $actor_url, 'actor profile URL is canonical');
    $check(($actor_document['manuallyApprovesFollowers'] ?? null) === false, 'actor states its follower-approval policy');
    $check(($actor_document['discoverable'] ?? null) === true, 'actor is discoverable');
    $check($isTimestamp($actor_document['published'] ?? null), 'actor published value is a timestamp');

    foreach (['inbox', 'outbox', 'followers', 'following', 'featured'] as $property) {
        $url = $actor_document[$property] ?? null;
        $check($isHTTPSURL($url), 'actor ' . $property . ' is an absolute HTTPS URL');
        $check(is_string($url) && $sameOrigin($url, $base), 'actor ' . $property . ' stays on the canonical origin');
    }

    $shared_inbox = $actor_document['endpoints']['sharedInbox'] ?? null;
    $check($isHTTPSURL($shared_inbox), 'actor advertises an HTTPS shared inbox');
    $check(is_string($shared_inbox) && $sameOrigin($shared_inbox, $base), 'shared inbox stays on the canonical origin');

    $public_key = is_array($actor_document['publicKey'] ?? null) ? $actor_document['publicKey'] : [];
    $check(($public_key['id'] ?? null) === $actor_url . '#main-key', 'actor key id is anchored to the actor');
    $check(($public_key['owner'] ?? null) === $actor_url, 'actor owns its advertised key');
    $parsed_key = is_string($public_key['publicKeyPem'] ?? null) ? openssl_pkey_get_public($public_key['publicKeyPem']) : false;
    $check($parsed_key !== false, 'actor public key is valid PEM');
    $key_details = $parsed_key !== false ? openssl_pkey_get_details($parsed_key) : false;
    $check(is_array($key_details) && ($key_details['type'] ?? null) === OPENSSL_KEYTYPE_RSA, 'actor public key is RSA');

    $negotiation_cases = [
        ['application/ld+json; profile="https://www.w3.org/ns/activitystreams"', 'application/activity+json', 'JSON-LD profile selects ActivityPub'],
        ['application/activity+json;q=0.9, text/html;q=0.2', 'application/activity+json', 'higher ActivityPub quality selects ActivityPub'],
        ['application/activity+json;q=0, text/html', 'text/html', 'q=0 forbids ActivityPub'],
        ['application/activity+json;q=0.5, text/html;q=0.9', 'text/html', 'higher HTML quality selects HTML'],
        ['text/html, application/activity+json', 'text/html', 'equal quality respects the first representation'],
        ['application/activity+json, text/html', 'application/activity+json', 'equal quality can prefer ActivityPub when it is first'],
        ['*/*', 'text/html', 'a wildcard request receives the ordinary profile'],
    ];

    foreach ($negotiation_cases as [$accept, $expected_type, $message]) {
        $response = $request($actor_url, 'GET', $accept);
        $check($response['status'] === 200, $message . ' and returns 200');
        $check($hasHeaderValue($response, 'Content-Type', $expected_type), $message);
        $check($hasHeaderValue($response, 'Vary', 'Accept'), $message . ' with Vary: Accept');
    }

    $unknown_actor = $request($base . '/users/' . rawurlencode($unknown) . '/', 'GET', 'application/activity+json');
    $check($unknown_actor['status'] === 404, 'an unknown actor returns 404');

    $section('Collections');

    foreach (['followers', 'following', 'featured'] as $property) {
        $url = (string) ($actor_document[$property] ?? '');
        $collection = $request($url);
        $checkActivityResponse($collection, $property . ' collection');
        $document = is_array($collection['json']) ? $collection['json'] : [];
        $items = is_array($document['orderedItems'] ?? null) ? $document['orderedItems'] : [];
        $check(($document['id'] ?? null) === $url, $property . ' collection id matches its URL');
        $check(($document['type'] ?? null) === 'OrderedCollection', $property . ' is an OrderedCollection');
        $check(($document['totalItems'] ?? null) === count($items), $property . ' totalItems matches its items');
        $check(count($items) === count(array_unique($items, SORT_REGULAR)), $property . ' contains no duplicate items');

        foreach ($items as $item) {
            $check($isHTTPSURL($item), $property . ' item is an absolute HTTPS URL');
        }
    }

    $inbox_url = (string) ($actor_document['inbox'] ?? '');
    $inbox = $request($inbox_url);
    $checkActivityResponse($inbox, 'member inbox GET');
    $check(($inbox['json']['id'] ?? null) === $inbox_url, 'member inbox id matches its URL');
    $check(($inbox['json']['type'] ?? null) === 'OrderedCollection', 'member inbox is an OrderedCollection');
    $check(($inbox['json']['totalItems'] ?? null) === 0, 'anonymous member inbox is empty');
    $check(($inbox['json']['orderedItems'] ?? null) === [], 'anonymous member inbox exposes no deliveries');

    $outbox_url = (string) ($actor_document['outbox'] ?? '');
    $outbox = $request($outbox_url);
    $checkActivityResponse($outbox, 'outbox collection');
    $outbox_document = is_array($outbox['json']) ? $outbox['json'] : [];
    $outbox_total = $outbox_document['totalItems'] ?? null;
    $check(($outbox_document['id'] ?? null) === $outbox_url, 'outbox id matches its URL');
    $check(($outbox_document['type'] ?? null) === 'OrderedCollection', 'outbox is an OrderedCollection');
    $check(is_int($outbox_total) && $outbox_total >= 0, 'outbox has a nonnegative integer total');
    $check(($outbox_total === 0) === !isset($outbox_document['first']), 'outbox advertises a first page exactly when nonempty');

    $first_page = null;
    $traversed = 0;
    $seen_activities = [];
    $next_page = is_string($outbox_document['first'] ?? null) ? $outbox_document['first'] : null;
    $previous_page = null;
    $page_count = 0;

    while ($next_page !== null && $page_count < 10) {
        $page_url = $next_page;
        $page = $request($page_url);
        $checkActivityResponse($page, 'outbox page ' . ($page_count + 1));
        $page_document = is_array($page['json']) ? $page['json'] : [];
        $items = is_array($page_document['orderedItems'] ?? null) ? $page_document['orderedItems'] : [];
        $first_page ??= $page_document;
        $check(($page_document['id'] ?? null) === $page_url, 'outbox page id matches the followed page URL');
        $check(($page_document['type'] ?? null) === 'OrderedCollectionPage', 'outbox page has the page type');
        $check(($page_document['partOf'] ?? null) === $outbox_url, 'outbox page points back to its collection');
        $check(($page_document['totalItems'] ?? null) === $outbox_total, 'outbox page repeats the collection total');
        $check(count($items) <= 20, 'outbox page never exceeds 20 activities');

        if ($previous_page === null) {
            $check(!isset($page_document['prev']), 'first outbox page has no previous link');
        } else {
            $check(($page_document['prev'] ?? null) === $previous_page, 'outbox previous link returns to the prior page');
        }

        foreach ($items as $activity) {
            $activity_id = is_array($activity) ? ($activity['id'] ?? null) : null;
            $check(is_string($activity_id) && !isset($seen_activities[$activity_id]), 'outbox activity has a unique id');

            if (is_string($activity_id)) {
                $seen_activities[$activity_id] = true;
            }
        }

        $traversed += count($items);
        $page_count++;
        $previous_page = $page_url;
        $next_page = is_string($page_document['next'] ?? null) ? $page_document['next'] : null;

        if ($next_page !== null) {
            $check($isHTTPSURL($next_page) && $sameOrigin($next_page, $base), 'outbox next link is canonical HTTPS');
        }
    }

    if ($next_page === null && is_int($outbox_total)) {
        $check($traversed === $outbox_total, 'traversing the outbox reaches exactly totalItems');
    } elseif ($next_page !== null) {
        $skip('outbox has more than 10 pages; traversal was deliberately bounded');
    }

    $normalised_page = $request($outbox_url . '?page=0');
    $check(($normalised_page['json']['id'] ?? null) === $outbox_url . '?page=1', 'outbox normalizes page zero to page one');
    $distant_page = $request($outbox_url . '?page=999999');
    $check(($distant_page['json']['type'] ?? null) === 'OrderedCollectionPage', 'an out-of-range outbox page remains a valid page');
    $check(($distant_page['json']['orderedItems'] ?? null) === [], 'an out-of-range outbox page is empty');
    $check(!isset($distant_page['json']['next']), 'an out-of-range outbox page has no next link');

    foreach (['POST', 'PUT', 'DELETE'] as $method) {
        $response = $request($outbox_url, $method);
        $check($response['status'] === 405, $method . ' to the read-only outbox returns 405');
        $check($hasHeaderValue($response, 'Allow', 'GET'), $method . ' outbox response advertises GET');
        $check($hasNoCookies($response), $method . ' outbox rejection is stateless');
    }

    $missing_collection = $request($base . '/users/' . rawurlencode($unknown) . '/outbox');
    $check($missing_collection['status'] === 404, 'an unknown member collection returns 404');
    $check($hasNoCookies($missing_collection), 'an unknown member collection is stateless');

    $section('Published activities and objects');

    if (is_array($first_page)) {
        $activities = is_array($first_page['orderedItems'] ?? null) ? $first_page['orderedItems'] : [];

        foreach ($activities as $index => $activity) {
            if (!is_array($activity)) {
                $check(false, 'outbox activity #' . ($index + 1) . ' is an object');
                continue;
            }

            $object = is_array($activity['object'] ?? null) ? $activity['object'] : null;
            $check(($activity['type'] ?? null) === 'Create', 'outbox activity #' . ($index + 1) . ' is Create');
            $check(($activity['actor'] ?? null) === $actor_url, 'outbox activity #' . ($index + 1) . ' belongs to the actor');
            $check($isHTTPSURL($activity['id'] ?? null), 'outbox activity #' . ($index + 1) . ' has an HTTPS id');
            $check($isTimestamp($activity['published'] ?? null), 'outbox activity #' . ($index + 1) . ' has a valid published timestamp');
            $check(is_array($object), 'outbox activity #' . ($index + 1) . ' embeds its object');

            if ($object === null) {
                continue;
            }

            $object_url = $object['id'] ?? null;
            $check($isHTTPSURL($object_url), 'outbox object #' . ($index + 1) . ' has an HTTPS id');
            $check(($object['attributedTo'] ?? null) === $actor_url, 'outbox object #' . ($index + 1) . ' is attributed to the actor');
            $check(in_array($object['type'] ?? null, ['Note', 'Article', 'Audio', 'Video', 'Question'], true), 'outbox object #' . ($index + 1) . ' has a supported type');
            $check(in_array('https://www.w3.org/ns/activitystreams#Public', $object['to'] ?? [], true), 'outbox object #' . ($index + 1) . ' is public');
            $check(in_array((string) ($actor_document['followers'] ?? ''), $object['cc'] ?? [], true), 'outbox object #' . ($index + 1) . ' addresses followers');

            foreach (($object['attachment'] ?? []) as $attachment) {
                if (is_array($attachment)) {
                    $attachment_url = is_array($attachment['url'] ?? null) ? ($attachment['url']['href'] ?? null) : ($attachment['url'] ?? null);
                    $check($isHTTPSURL($attachment_url), 'outbox attachment has an HTTPS URL');
                    $check(is_string($attachment['mediaType'] ?? null) && str_contains($attachment['mediaType'], '/'), 'outbox attachment has a media type');
                }
            }

            if ($index === 0 && is_string($object_url)) {
                $standalone = $request($object_url, 'GET', 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
                $checkActivityResponse($standalone, 'standalone post');
                $standalone_document = is_array($standalone['json']) ? $standalone['json'] : [];

                foreach (['id', 'type', 'attributedTo', 'published', 'content', 'to', 'cc'] as $property) {
                    if (array_key_exists($property, $object)) {
                        $check(($standalone_document[$property] ?? null) === $object[$property], 'standalone post preserves ' . $property);
                    }
                }

                $standalone_html = $request($object_url, 'GET', 'text/html');
                $check($standalone_html['status'] === 200, 'standalone post URL also serves HTML');
                $check($hasHeaderValue($standalone_html, 'Content-Type', 'text/html'), 'standalone post negotiates HTML');
                $check($hasHeaderValue($standalone_html, 'Vary', 'Accept'), 'standalone post HTML varies on Accept');
            }
        }
    } else {
        $skip('actor has no public posts to validate');
    }

    $section('Inbox authentication boundary');
    $valid_digest = 'SHA-256=' . base64_encode(hash('sha256', '{}', true));
    $now = gmdate('D, d M Y H:i:s') . ' GMT';
    $inbox_rejections = [
        ['missing authentication', '{}', []],
        ['stale Date', '{}', ['Date: ' . gmdate('D, d M Y H:i:s', time() - 7200) . ' GMT', 'Digest: ' . $valid_digest, 'Signature: malformed']],
        ['future Date', '{}', ['Date: ' . gmdate('D, d M Y H:i:s', time() + 7200) . ' GMT', 'Digest: ' . $valid_digest, 'Signature: malformed']],
        ['incorrect Digest', '{}', ['Date: ' . $now, 'Digest: SHA-256=not-the-body', 'Signature: malformed']],
        ['malformed Signature', '{}', ['Date: ' . $now, 'Digest: ' . $valid_digest, 'Signature: malformed']],
    ];

    foreach ($inbox_rejections as [$name, $body, $headers]) {
        $response = $request($inbox_url, 'POST', 'application/activity+json', $body, array_merge(['Content-Type: application/activity+json'], $headers));
        $check($response['status'] === 401, 'inbox rejects ' . $name);
        $check($hasNoCookies($response), 'inbox rejection for ' . $name . ' is stateless');
    }

    $oversized_response = $request($inbox_url, 'POST', 'application/activity+json', str_repeat('x', 262145), ['Content-Type: application/activity+json']);
    $check($oversized_response['status'] === 413, 'inbox rejects a body over 256 KiB');
    $check($hasNoCookies($oversized_response), 'oversized inbox rejection is stateless');

    foreach (['GET', 'PUT', 'DELETE'] as $method) {
        $response = $request((string) $shared_inbox, $method);
        $check($response['status'] === 405, $method . ' to the shared inbox returns 405');
        $check($hasHeaderValue($response, 'Allow', 'POST'), $method . ' shared-inbox response advertises POST');
        $check($hasNoCookies($response), $method . ' shared-inbox rejection is stateless');
    }

    $section('Instance actor and NodeInfo');
    $instance_actor_url = $base . '/activitypub/actor';
    $instance_actor = $request($instance_actor_url);
    $checkActivityResponse($instance_actor, 'instance actor');
    $instance_document = is_array($instance_actor['json']) ? $instance_actor['json'] : [];
    $check(($instance_document['id'] ?? null) === $instance_actor_url, 'instance actor id matches its URL');
    $check(($instance_document['type'] ?? null) === 'Application', 'instance actor is an Application');
    $check(($instance_document['inbox'] ?? null) === $shared_inbox, 'instance actor uses the shared inbox');
    $check($isHTTPSURL($instance_document['outbox'] ?? null), 'instance actor advertises an HTTPS outbox');

    if (is_string($instance_document['outbox'] ?? null)) {
        $instance_outbox = $request($instance_document['outbox']);
        $checkActivityResponse($instance_outbox, 'instance outbox');
        $check(($instance_outbox['json']['type'] ?? null) === 'OrderedCollection', 'instance outbox is an OrderedCollection');
        $check(($instance_outbox['json']['totalItems'] ?? null) === 0, 'instance outbox is empty');
    }

    $nodeinfo = $request($base . '/.well-known/nodeinfo', 'GET', 'application/json');
    $check($nodeinfo['status'] === 200, 'NodeInfo discovery returns 200');
    $check($hasHeaderValue($nodeinfo, 'Content-Type', 'application/json'), 'NodeInfo discovery uses JSON');
    $check($hasNoCookies($nodeinfo), 'NodeInfo discovery is stateless');
    $nodeinfo_links = is_array($nodeinfo['json']['links'] ?? null) ? $nodeinfo['json']['links'] : [];
    $nodeinfo_link = null;

    foreach ($nodeinfo_links as $link) {
        if (is_array($link) && ($link['rel'] ?? null) === 'http://nodeinfo.diaspora.software/ns/schema/2.0') {
            $nodeinfo_link = $link['href'] ?? null;
            break;
        }
    }

    $check($isHTTPSURL($nodeinfo_link), 'NodeInfo discovery advertises its 2.0 document');
    $check(is_string($nodeinfo_link) && $sameOrigin($nodeinfo_link, $base), 'NodeInfo document stays on the canonical origin');

    if (is_string($nodeinfo_link)) {
        $nodeinfo_response = $request($nodeinfo_link, 'GET', 'application/json');
        $check($nodeinfo_response['status'] === 200, 'NodeInfo 2.0 document returns 200');
        $check($hasHeaderValue($nodeinfo_response, 'Content-Type', 'application/json'), 'NodeInfo 2.0 document uses JSON');
        $check($hasNoCookies($nodeinfo_response), 'NodeInfo 2.0 document is stateless');
        $document = is_array($nodeinfo_response['json']) ? $nodeinfo_response['json'] : [];
        $check(($document['version'] ?? null) === '2.0', 'NodeInfo reports schema version 2.0');
        $check(($document['software']['name'] ?? null) === 'glommer', 'NodeInfo identifies Glommer');
        $check(is_string($document['software']['version'] ?? null) && $document['software']['version'] !== '', 'NodeInfo reports a software version');
        $check(in_array('activitypub', $document['protocols'] ?? [], true), 'NodeInfo advertises ActivityPub');
        $check(is_bool($document['openRegistrations'] ?? null), 'NodeInfo reports whether registrations are open');
        $check(is_int($document['usage']['users']['total'] ?? null) && $document['usage']['users']['total'] >= 0, 'NodeInfo reports a nonnegative user count');
        $check(is_int($document['usage']['localPosts'] ?? null) && $document['usage']['localPosts'] >= 0, 'NodeInfo reports a nonnegative local-post count');
        $check(is_array($document['services']['inbound'] ?? null), 'NodeInfo supplies inbound services');
        $check(is_array($document['services']['outbound'] ?? null), 'NodeInfo supplies outbound services');
    }

    foreach ([$base . '/.well-known/nodeinfo', $base . '/nodeinfo/2.0', $instance_actor_url] as $read_only_url) {
        $response = $request($read_only_url, 'POST', 'application/json');
        $check($response['status'] === 405, $read_only_url . ' rejects POST');
        $check($hasHeaderValue($response, 'Allow', 'GET'), $read_only_url . ' advertises GET');
        $check($hasNoCookies($response), $read_only_url . ' method rejection is stateless');
    }
} catch (Throwable $exception) {
    $fail($exception -> getMessage());
}

echo "\n$passed passed, $failed failed, $skipped skipped.\n";
exit($failed === 0 ? 0 : 1);
