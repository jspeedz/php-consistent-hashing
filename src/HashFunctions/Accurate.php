<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\HashFunctions;

/**
 * 5 probes.
 *
 * This one is a bit more accurate that the Standard version.
 * But also, slower.
 */
class Accurate {
    /**
     * @return callable[]
     */
    public function __invoke(): array {
        return [
            function(string $key): int {
                return crc32($key);
            },
            function(string $key): int|float {
                return hexdec(substr(hash('sha1', $key), 0, 8));
            },
            function(string $key): float|int {
                return hexdec(substr(hash('sha256', $key), 0, 8));
            },
            function(string $key): int|float {
                return hexdec(substr(hash('md4', $key), 0, 8));
            },
            function(string $key): float|int {
                return hexdec(substr(hash('md5', $key), 0, 8));
            },
        ];
    }
}
