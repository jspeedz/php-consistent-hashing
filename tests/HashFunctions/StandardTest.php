<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\Tests\HashFunctions;

use Jspeedz\PhpConsistentHashing\HashFunctions\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Standard::class)]
class StandardTest extends TestCase {
    public function testStandardCallbacks(): void {
        $callbacks = (new Standard())();

        $this->assertCount(3, $callbacks);

        $this->assertIsCallable($callbacks[0]);
        $this->assertIsCallable($callbacks[1]);
        $this->assertIsCallable($callbacks[2]);

        $this->assertSame(
            crc32('test'),
            $callbacks[0]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('md5', 'test'), 0, 8)),
            $callbacks[1]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('sha256', 'test'), 0, 8)),
            $callbacks[2]('test'),
        );
    }
}