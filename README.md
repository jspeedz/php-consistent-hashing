# php-consistent-hashing
Multi-probe consistent hashing, using php

The idea behind this package is that you can distribute keys to nodes in a consistent/sticky way, while also allowing for custom weights for each node.
This allows you to route 'traffic' to nodes depending on an equal or custom distibution.

The algorithm should distribute more evenly than for example libketama, which can have a deviation between distributions of equal weight as big as about 10%.
For more information on this algorithm and consistent hashing in general, see:
- https://arxiv.org/abs/1505.00062
- https://en.wikipedia.org/wiki/Consistent_hashing

Use cases:
- Load balancing.
- Distributing users to groups, for example for use in A/B testing.

Example usage:
```php
$hasher = new \Jspeedz\PhpConsistentHashing\MultiProbeConsistentHash();
// Choose which hash functions to use
$hasher->setHashFunctions((new \Jspeedz\PhpConsistentHashing\HashFunctions\Standard)());
```

Usage with equal weights:
```php
$hasher->addNode('node1');
$hasher->addNode('node2');
$hasher->addNode('node2');

$node = $hasher->hash('some_key1');
```

Usage with custom weights (I like to use percentages):
```php
$hasher->addNode('node1', 25);
$hasher->addNode('node2', 33.3);
$hasher->addNode('node2', 41.7);

$node = $hasher->hash('some_key1');
```
Note: if you add a node without specifying a weight, the weight will be set to 1.