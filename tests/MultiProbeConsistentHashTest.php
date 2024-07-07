<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\Tests;

use Exception;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Jspeedz\PhpConsistentHashing\MultiProbeConsistentHash;

#[CoversClass(MultiProbeConsistentHash::class)]
class MultiProbeConsistentHashTest extends TestCase {
    public function testSetHashFunctions(): void {
        $hash = new MultiProbeConsistentHash();
        $hashFunctions = [
            function($key) { return crc32($key); },
            function($key) { return crc32(strrev($key)); }
        ];

        $hash->setHashFunctions($hashFunctions);

        $reflection = new \ReflectionClass($hash);
        $hashFunctionsProperty = $reflection->getProperty('hashFunctions');
        $hashFunctionsProperty->setAccessible(true);

        $this->assertSame($hashFunctions, $hashFunctionsProperty->getValue($hash));
    }

    public function testAddNode(): void {
        $hash = new MultiProbeConsistentHash();
        $hash->addNode('node1', 1.5);

        $reflection = new \ReflectionClass($hash);
        $nodesProperty = $reflection->getProperty('nodes');
        $nodesProperty->setAccessible(true);

        $totalWeightProperty = $reflection->getProperty('totalWeight');
        $totalWeightProperty->setAccessible(true);

        /** @var array<string, float> $nodes */
        $nodes = $nodesProperty->getValue($hash);
        $totalWeight = $totalWeightProperty->getValue($hash);

        $this->assertArrayHasKey('node1', $nodes);
        $this->assertEquals(1.5, $nodes['node1']);
        $this->assertEquals(1.5, $totalWeight);
    }

    public function testRemoveNode(): void {
        $hash = new MultiProbeConsistentHash();
        $hash->addNode('node1', 1.5);
        $hash->removeNode('node1');

        $reflection = new \ReflectionClass($hash);
        $nodesProperty = $reflection->getProperty('nodes');
        $nodesProperty->setAccessible(true);

        $totalWeightProperty = $reflection->getProperty('totalWeight');
        $totalWeightProperty->setAccessible(true);

        /** @var array<string, float> $nodes */
        $nodes = $nodesProperty->getValue($hash);
        $totalWeight = $totalWeightProperty->getValue($hash);

        $this->assertArrayNotHasKey('node1', $nodes);
        $this->assertEquals(0, $totalWeight);
    }

    public function testGetNode(): void {
        $hash = new MultiProbeConsistentHash();

        $hashFunctions = [
            function($key) { return crc32($key); },
            function($key) { return crc32(strrev($key)); }
        ];

        $hash->setHashFunctions($hashFunctions);
        $hash->addNode('node1', 1.5);
        $hash->addNode('node2', 2.0);

        $node = $hash->getNode('myKey');

        $this->assertContains($node, ['node1', 'node2']);
    }

    public function testGetNodeWithNoNodes(): void {
        $hash = new MultiProbeConsistentHash();

        $hashFunctions = [
            function($key) { return crc32($key); },
            function($key) { return crc32(strrev($key)); }
        ];

        $hash->setHashFunctions($hashFunctions);

        $node = $hash->getNode('myKey');

        $this->assertNull($node);
    }

    /**
     * @param float $maximumAllowedDeviationPercentage
     * @param array<string, float> $nodes
     * @param array<string> $keys
     */
    #[DataProvider('distributionDataProvider')]
    public function testDistribution(
        float $maximumAllowedDeviationPercentage,
        array $nodes,
        array $keys,
    ): void {
        $hash = new MultiProbeConsistentHash();

        $hash->setHashFunctions([
            function(string $key): int {
                return crc32($key);
            },
            function(string $key): float|int {
                return hexdec(substr(hash('md5', $key), 0, 8));
            },
            function(string $key): float|int {
                return hexdec(substr(hash('sha256', $key), 0, 8));
            },
        ]);
        $distribution = [];
        foreach($nodes as $node => $weight) {
            $hash->addNode($node, $weight);
            $distribution[$node] = [];
        }

        $runCount = 3;
        for($i = 0; $i < $runCount; $i++) {
            shuffle($keys);

            foreach($keys as $key) {
                $pickedNode = $hash->getNode($key);
                $distribution[$pickedNode][$key] ??= 0;
                $distribution[$pickedNode][$key] += 1;

                foreach($nodes as $node => $weight) {
                    if($node === $pickedNode) continue;

                    if(isset($distribution[$node][$key])) {
                        $this->fail('Key has been distributed to multiple nodes, this is not what I call sticky!!');
                    }
                }
            }
        }

        // Count the number of keys assigned to each node
        foreach($distribution as &$keys) {
            $sum = array_sum($keys);
            $keys = count($keys);

            // Make sure the actual counts match up
            $this->assertSame($runCount * $keys, $sum);
        }
        unset($keys);

        // Turn the absolute numbers into percentages
        $totalWeight = array_sum($nodes);
        foreach($nodes as &$weight) {
            $weight = $weight / $totalWeight * 100;
        }

        $total = array_sum($distribution);
        foreach($distribution as &$count) {
            $count = $count / $total * 100;
        }

        // Compare the expected distribution with the actual distribution
        foreach($nodes as $node => $expectedDistributionPercentage) {
            // Unfortunately PHPStan doesn't get what is going on here (Or I don't)
            // @phpstan-ignore binaryOp.invalid
            $deviation = $expectedDistributionPercentage - $distribution[$node];
            $deviation = abs($deviation);

            $this->assertThat(
                $deviation,
                $this->logicalAnd(
                    $this->greaterThanOrEqual(0), // Zero would be fantastic, but..
                    $this->lessThan($maximumAllowedDeviationPercentage)
                )
            );
        }
    }

    public static function distributionDataProvider(): Generator {
        $keys = file_get_contents(__DIR__ . '/data/random_ip_addresses.json');
        if(!$keys) {
            throw new Exception('Could not read random_ip_addresses.json');
        }
        $keys = json_decode(
            $keys,
            true,
            JSON_THROW_ON_ERROR,
        );

        yield 'IP keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.3,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $keys,
        ];

        yield 'IP keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.4,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $keys,
        ];

        yield 'IP keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.3,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $keys,
        ];

        yield 'IP keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.6,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $keys,
        ];

        yield 'IP keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 2.8,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $keys,
        ];

        yield 'IP keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.8,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $keys,
        ];

        # -------------------
        # -------------------

        $keys = file_get_contents(__DIR__ . '/data/random_strings.json');
        if(!$keys) {
            throw new Exception('Could not read random_strings.json');
        }
        $keys = json_decode(
            $keys,
            true,
            JSON_THROW_ON_ERROR,
        );

        yield 'Random string keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.2,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $keys,
        ];

        yield 'Random string keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.3,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $keys,
        ];

        yield 'Random string keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.3,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $keys,
        ];

        yield 'Random string keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.5,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $keys,
        ];

        yield 'Random string keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 2.9,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $keys,
        ];

        yield 'Random string keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.8,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $keys,
        ];
    }
}
