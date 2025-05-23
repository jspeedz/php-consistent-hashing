<?php declare(strict_types=1);

namespace Jspeedz\PhpConsistentHashing;

class MultiProbeConsistentHash {
    /**
     * @var array<string, float> $nodes
     */
    private array $nodes = [];
    private float $totalWeight = 0;
    /**
     * @var callable[] $hashFunctions
     */
    private array $hashFunctions;

    /**
     * @param callable[] $hashFunctions
     */
    public function setHashFunctions(array $hashFunctions): void {
        $this->hashFunctions = array_values($hashFunctions);
    }

    /**
     * @param array<string, null|float> $nodes Node names as keys and weights as values.
     *                                         If weight is null, it will be set to 1.
     */
    public function addNodes(array $nodes): void {
        foreach($nodes as $node => $weight) {
            if($weight === null) {
                $weight = 1.0;
            }

            $this->addNode($node, $weight);
        }
    }

    public function addNode(?string $node, float $weight = 1): void {
        $this->nodes[$node] = $weight;
        $this->totalWeight += $weight;
    }

    public function removeNode(string $node): void {
        if(isset($this->nodes[$node])) {
            $this->totalWeight -= $this->nodes[$node];
            unset($this->nodes[$node]);
        }
    }

    public function getNode(string $key): ?string {
        if(empty($this->nodes)) {
            return null;
        }

        $minHash = PHP_INT_MAX;
        $targetNode = null;

        foreach($this->nodes as $node => $weight) {
            foreach($this->hashFunctions as $hashFunction) {
                $hash = $hashFunction($key . $node);
                // Adjust hash by weight
                $weightedHash = $hash / $weight;
                if($weightedHash < $minHash) {
                    $minHash = $weightedHash;
                    $targetNode = $node;
                }
            }
        }

        return $targetNode;
    }
}
