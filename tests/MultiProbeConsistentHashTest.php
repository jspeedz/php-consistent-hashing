<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing\Tests;

use Exception;
use Generator;
use Jspeedz\PhpConsistentHashing\HashFunctions\Accurate;
use Jspeedz\PhpConsistentHashing\HashFunctions\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Jspeedz\PhpConsistentHashing\MultiProbeConsistentHash;

#[CoversClass(MultiProbeConsistentHash::class)]
#[UsesClass(Standard::class)]
#[UsesClass(Accurate::class)]
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

    public function testAddNodes(): void {
        $hash = $this->getMockBuilder(MultiProbeConsistentHash::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'addNode',
            ])
            ->getMock();

        $matcher = $this->exactly(3);
        $hash->expects($matcher)
            ->method('addNode')
            ->willReturnCallback(function(string $node, ?float $weight) use ($matcher) {
                switch($matcher->numberOfInvocations()) {
                    case 1:
                        $this->assertEquals('node1', $node);
                        $this->assertEquals(1.5, $weight);
                        break;
                    case 2:
                        $this->assertEquals('node2', $node);
                        $this->assertEquals(2.0, $weight);
                        break;
                    case 3:
                        $this->assertEquals('node3', $node);
                        $this->assertEquals(1.0, $weight);
                        break;
                };
            });

        $hash->addNodes([
            'node1' => 1.5,
            'node2' => 2.0,
            'node3' => null,
        ]);
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
     * @param callable[] $hashFunctions
     * @param array<string, float> $nodes
     * @param array<string> $keys
     */
    #[DataProvider('distributionDataProvider')]
    public function testDistribution(
        float $maximumAllowedDeviationPercentage,
        array $hashFunctions,
        array $nodes,
        array $keys,
    ): void {
        $hash = new MultiProbeConsistentHash();

        $hash->setHashFunctions($hashFunctions);
        $distribution = [];
        foreach($nodes as $node => $weight) {
            $hash->addNode($node, $weight);
        }

        $runCount = 3;
        for($i = 0; $i < $runCount; $i++) {
            shuffle($keys);

            foreach($keys as $key) {
                $pickedNode = $hash->getNode($key);
                $distribution[$pickedNode] ??= [];
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

        $this->assertCount(count($nodes), $distribution, 'Did not pick all nodes');

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
        $deviations = [];

        foreach($nodes as $node => $expectedDistributionPercentage) {
            // Unfortunately PHPStan doesn't get what is going on here (Or I don't)
            // @phpstan-ignore binaryOp.invalid
            $deviation = $expectedDistributionPercentage - $distribution[$node];
            $deviation = abs($deviation);
            $deviations[$node] = $deviation;

        }
        foreach($deviations as $deviation) {
            $this->assertThat(
                $deviation,
                $this->logicalAnd(
                    $this->greaterThanOrEqual(0), // Zero would be fantastic, but..
                    $this->lessThan($maximumAllowedDeviationPercentage),
                ),
                sprintf(
                    'Max detected deviation: %F',
                    max($deviations),
                ),
            );
        }

        $average = array_sum($deviations) / count($deviations);
        $deviationRange = max($deviations) - min($deviations);
        $this->assertLessThanOrEqual(
            3,
            $deviationRange,
            'The deviation range should at least stay within 3% of each other',
        );
    }

    public static function distributionDataProvider(): Generator {
        $oneHundredAndTwentyNodes = null;
//        $oneHundredAndTwentyNodes = array_combine(
//            array_map(
//                fn(int $i): string => 'node' . $i,
//                range(1, 120),
//            ),
//            array_fill(
//                0,
//                120,
//                100/120,
//            ),
//        );

        $randomIpAddresses = file_get_contents(__DIR__ . '/data/random_ip_addresses.json');
        if(!$randomIpAddresses) {
            throw new Exception('Could not read random_ip_addresses.json');
        }
        $randomIpAddresses = json_decode(
            $randomIpAddresses,
            true,
            JSON_THROW_ON_ERROR,
        );
        $ascendingNumberKeys = array_map('strval', range(1, 15000));

        $randomStrings = file_get_contents(__DIR__ . '/data/random_strings.json');
        if(!$randomStrings) {
            throw new Exception('Could not read random_strings.json');
        }
        $randomStrings = json_decode(
            $randomStrings,
            true,
            JSON_THROW_ON_ERROR,
        );

        $hashFunctions = (new Standard)();

        yield 'Standard - IP keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.3,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Standard - IP keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.4,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Standard - IP keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.3,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Standard - IP keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.63,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Standard - IP keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 2.92,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Standard - IP keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.8,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $randomIpAddresses,
        ];

        if(isset($oneHundredAndTwentyNodes)) {
            yield 'Standard - IP keys - Equal distribution - 120 nodes (make sure < 1 weights work)' => [
                'maximumAllowedDeviationPercentage' => 0.14,
                'hashFunctions' => $hashFunctions,
                'nodes' => $oneHundredAndTwentyNodes,
                'keys' => $randomIpAddresses,
            ];
        }

        # -------------------
        # -------------------

        yield 'Standard - Random string keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.31,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Standard - Random string keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.3,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Standard - Random string keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.3,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Standard - Random string keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.58,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Standard - Random string keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 2.92,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Standard - Random string keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.8,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Standard - IP keys - Equal distribution - 12 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.91,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
                'node5' => 1,
                'node6' => 1,
                'node7' => 1,
                'node8' => 1,
                'node9' => 1,
                'node10' => 1,
                'node11' => 1,
                'node12' => 1,
            ],
            'keys' => $randomStrings,
        ];

        if(isset($oneHundredAndTwentyNodes)) {
            yield 'Standard - Random string keys - Equal distribution - 150 nodes (make sure < 1 weights work)' => [
                'maximumAllowedDeviationPercentage' => 0.8,
                'hashFunctions' => $hashFunctions,
                'nodes' => $oneHundredAndTwentyNodes,
                'keys' => $randomStrings,
            ];
        }

        # -------------------
        # -------------------

        yield 'Standard - Ascending numbers keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.28,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Standard - Ascending numbers keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.33,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Standard - Ascending numbers keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.34,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Standard - Ascending numbers keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.46,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Standard - Ascending numbers keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 2.6,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Standard - Ascending numbers keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.29,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Standard - Ascending numbers keys - Equal distribution - 12 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.9,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
                'node5' => 1,
                'node6' => 1,
                'node7' => 1,
                'node8' => 1,
                'node9' => 1,
                'node10' => 1,
                'node11' => 1,
                'node12' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        if(isset($oneHundredAndTwentyNodes)) {
            yield 'Standard - Ascending numbers keys - Equal distribution - 150 nodes (make sure < 1 weights work)' => [
                'maximumAllowedDeviationPercentage' => 0.28,
                'hashFunctions' => $hashFunctions,
                'nodes' => $oneHundredAndTwentyNodes,
                'keys' => $ascendingNumberKeys,
            ];
        }

        // --------------
        // --------------

        $hashFunctions = (new Accurate())();

        yield 'Accurate - IP keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.12,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Accurate - IP keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.1,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Accurate - IP keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.26,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Accurate - IP keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.47,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Accurate - IP keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.5,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $randomIpAddresses,
        ];

        yield 'Accurate - IP keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.22,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $randomIpAddresses,
        ];

        if(isset($oneHundredAndTwentyNodes)) {
            yield 'Accurate - IP keys - Equal distribution - 120 nodes (make sure < 1 weights work)' => [
                'maximumAllowedDeviationPercentage' => 0.13,
                'hashFunctions' => $hashFunctions,
                'nodes' => $oneHundredAndTwentyNodes,
                'keys' => $randomIpAddresses,
            ];
        }

        # -------------------
        # -------------------

        yield 'Accurate - Random string keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.15,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Accurate - Random string keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.18,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Accurate - Random string keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.22,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Accurate - Random string keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.43,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Accurate - Random string keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.64,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Accurate - Random string keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 3.7,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $randomStrings,
        ];

        yield 'Accurate - Random string keys - Equal distribution - 12 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.38,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
                'node5' => 1,
                'node6' => 1,
                'node7' => 1,
                'node8' => 1,
                'node9' => 1,
                'node10' => 1,
                'node11' => 1,
                'node12' => 1,
            ],
            'keys' => $randomStrings,
        ];

        if(isset($oneHundredAndTwentyNodes)) {
            yield 'Accurate - Random string keys - Equal distribution - 120 nodes (make sure < 1 weights work)' => [
                'maximumAllowedDeviationPercentage' => 0.35,
                'hashFunctions' => $hashFunctions,
                'nodes' => $oneHundredAndTwentyNodes,
                'keys' => $randomStrings,
            ];
        }

        # -------------------
        # -------------------

        yield 'Accurate - Ascending numbers keys - Equal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.45,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Accurate - Ascending numbers keys - Equal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.13,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Accurate - Ascending numbers keys - Equal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.65,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Accurate - Ascending numbers keys - Unequal distribution - 2 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.05,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 25,
                'node2' => 75,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Accurate - Ascending numbers keys - Unequal distribution - 3 nodes' => [
            'maximumAllowedDeviationPercentage' => 1.38,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 70,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Accurate - Ascending numbers keys - Unequal distribution - 4 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.8,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 5,
                'node2' => 25,
                'node3' => 30,
                'node4' => 40,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        yield 'Accurate - Ascending numbers keys - Equal distribution - 12 nodes' => [
            'maximumAllowedDeviationPercentage' => 0.67,
            'hashFunctions' => $hashFunctions,
            'nodes' => [
                'node1' => 1,
                'node2' => 1,
                'node3' => 1,
                'node4' => 1,
                'node5' => 1,
                'node6' => 1,
                'node7' => 1,
                'node8' => 1,
                'node9' => 1,
                'node10' => 1,
                'node11' => 1,
                'node12' => 1,
            ],
            'keys' => $ascendingNumberKeys,
        ];

        if(isset($oneHundredAndTwentyNodes)) {
            yield 'Accurate - Ascending numbers keys - Equal distribution - 120 nodes (make sure < 1 weights work)' => [
                'maximumAllowedDeviationPercentage' => 2.4,
                'hashFunctions' => $hashFunctions,
                'nodes' => $oneHundredAndTwentyNodes,
                'keys' => $ascendingNumberKeys,
            ];
        }
    }

    public function testStickynessOnNodeDeletions(): void {
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

        $hash->addNode('toBeDeleted');
        $hash->addNode('node1');
        $hash->addNode('node2');

        $winningNodeDeleted = $hash->getNode('key5');
        $winningNodeNonDeletedNode1 = $hash->getNode('key1');
        $winningNodeNonDeletedNode2 = $hash->getNode('key2');
        $this->assertSame($winningNodeDeleted, 'toBeDeleted');
        $this->assertSame($winningNodeNonDeletedNode1, 'node1');
        $this->assertSame($winningNodeNonDeletedNode2, 'node2');

        $hash->removeNode('toBeDeleted');

        $winningNodeDeleted = $hash->getNode('key5');
        $winningNodeNonDeletedNode1 = $hash->getNode('key1');
        $winningNodeNonDeletedNode2 = $hash->getNode('key2');
        $this->assertSame($winningNodeDeleted, 'node2'); // Is now reassigned a new node
        $this->assertSame($winningNodeNonDeletedNode1, 'node1'); // In the same node!
        $this->assertSame($winningNodeNonDeletedNode2, 'node2'); // In the same node!
    }
}
