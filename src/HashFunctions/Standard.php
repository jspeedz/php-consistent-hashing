<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\HashFunctions;

class Standard {
    /**
     * @return callable[]
     */
    public function __invoke(): array {
        return [
            function(string $key): int {
                return crc32($key);
            },
            function(string $key): float|int {
                return hexdec(substr(hash('md5', $key), 0, 8));
            },
            function(string $key): float|int {
                return hexdec(substr(hash('sha256', $key), 0, 8));
            },
        ];
    }
}