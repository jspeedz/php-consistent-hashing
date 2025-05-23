<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\HashFunctions;

/**
 * 3 probes.
 *
 * This one has a good balance between performance and accuracy.
 */
class Standard {
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
            function(string $key): int|float {
                return hexdec(substr(hash('md4', $key), 0, 8));
            },
        ];
    }
}
