<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\Tests\HashFunctions;

use Jspeedz\PhpConsistentHashing\HashFunctions\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Standard::class)]
class StandardTest extends TestCase {
    #[Test]
    public function standardCallbacks(): void {
        $callbacks = (new Standard())();

        $this->assertCount(3, $callbacks);

        $this->assertSame(
            crc32('test'),
            $callbacks[0]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('sha1', 'test'), 0, 8)),
            $callbacks[1]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('md4', 'test'), 0, 8)),
            $callbacks[2]('test'),
        );
    }
}
