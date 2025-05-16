<?php declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$amount = 50000;
$targetDir = __DIR__ . '/';

foreach($argv as $item) {
    if(preg_match('/--amount=(?<amount>[0-9]+)/', $item, $matches)) {
        $amount = (int) $matches['amount'];
    }
    if(preg_match('/--target-dir=(?<targetDir>[a-zA-Z0-9\/.\-]+)/', $item, $matches)) {
        $targetDir = $matches['targetDir'];
    }
}

$generatorCallbacks = [
    'random_ip_addresses.json' => function(): false|string {
        return long2ip(rand(0, PHP_INT_MAX));
    },
    'random_strings.json' => function(): string {
        return bin2hex(random_bytes(10));
    },
];

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

foreach($generatorCallbacks as $fileName => $callback) {
    $tries = 1;
    do {
        $valid = true;

        $keys = null;
        try {
            $keys = generateItems($amount, $callback);
        }
        catch(Exception) {
            $valid = false;
        }

        if($keys === null || count($keys) !== count(array_unique($keys))) {
            $valid = false;
            if($tries > 10) {
                throw new Exception('Duplicate items found, please try again');
            }
        }
        $tries++;
    } while(!$valid);

    file_put_contents($targetDir . $fileName, json_encode($keys));
}
