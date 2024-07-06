<?php declare(strict_types=1);

$amount = 100000;

# -------------------
# -------------------

/**
 * @return array<string>
 */
function generateItems(int $count, callable $callback): array {
    $keys = [];
    for ($i = 0; $i < $count; $i++) {
        $key = $callback();
        if($key) {
            $keys[] = $key;
        }
    }

    if(count($keys) !== $count) {
        throw new Exception(
            'Failed to generate the required amount of items, please try again',
        );
    }

    return $keys;
}

$keys = generateItems($amount, function(): false|string {
    return long2ip(rand(0, PHP_INT_MAX));
});
if(count($keys) !== count(array_unique($keys))) {
    throw new Exception('Duplicate IP addresses found, please try again');
}
file_put_contents(__DIR__ . '/random_ip_addresses.json', json_encode($keys));

unset($ipAddresses);

$keys = generateItems($amount, function(): string {
    return bin2hex(random_bytes(10));
});
if(count($keys) !== count(array_unique($keys))) {
    throw new Exception('Duplicate random strings found, please try again');
}
file_put_contents(__DIR__ . '/random_strings.json', json_encode($keys));