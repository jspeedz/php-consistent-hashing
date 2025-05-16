<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\Tests\HashFunctions;

use Jspeedz\PhpConsistentHashing\HashFunctions\Accurate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Accurate::class)]
class AccurateTest extends TestCase {
    public function testStandardCallbacks(): void {
        $callbacks = (new Accurate())();

        $this->assertCount(5, $callbacks);

        $this->assertIsCallable($callbacks[0]);
        $this->assertIsCallable($callbacks[1]);
        $this->assertIsCallable($callbacks[2]);
        $this->assertIsCallable($callbacks[3]);
        $this->assertIsCallable($callbacks[4]);

        $this->assertSame(
            crc32('test'),
            $callbacks[0]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('sha1', 'test'), 0, 8)),
            $callbacks[1]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('sha256', 'test'), 0, 8)),
            $callbacks[2]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('md4', 'test'), 0, 8)),
            $callbacks[3]('test'),
        );
        $this->assertSame(
            hexdec(substr(hash('md5', 'test'), 0, 8)),
            $callbacks[4]('test'),
        );
    }
}
