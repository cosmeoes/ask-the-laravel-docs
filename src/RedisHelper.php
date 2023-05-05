<?php

namespace CosmeDev\AskDocs;

use MacFJA\RediSearch\IndexBuilder;
use MacFJA\RediSearch\Redis\Client\ClientFacade;
use MacFJA\RediSearch\Redis\Command\AbstractCommand;
use MacFJA\RediSearch\Redis\Command\Search;
use Predis\Client;

class RedisHelper
{
    public $redisSearchClient;
    public $predisClient;

    public function __construct()
    {
        $clientFacade = new ClientFacade();

        $this->predisClient = new Client([
            'scheme' => config('redis.scheme'),
            'host'   => config('redis.host'),
            'port'   => config('redis.port'),
        ]);

        // Check Connection
        $this->predisClient->ping();

        $this->redisSearchClient = $clientFacade->getClient(new Client([
            'scheme' => config('redis.scheme'),
            'host'   => config('redis.host'),
            'port'   => config('redis.port'),
        ]));
    }

    public function hasIndex($name): bool
    {
        $result = $this->redisSearchClient->executeRaw('FT._LIST');
        return in_array($name, $result);
    }

    public function createIndex(IndexBuilder $indexBuilder)
    {
        $indexBuilder->create($this->redisSearchClient, AbstractCommand::MAX_IMPLEMENTED_VERSION);
    }

    public function hset($key, $document)
    {
        foreach ($document as $field => $value) {
            $this->predisClient->hset($key, $field, $value);
        }
    }

    public function vectorSearch(string $indexName, int $k, string $blobVector, string $vectorName, array $returnFields)
    {
        $rawDocs = $this->predisClient->executeRaw([
            'FT.SEARCH',
            $indexName,
            '*=>[KNN ' . $k . ' @' . $vectorName . ' $vector as vector_score]',
            "PARAMS", "2", "vector", $blobVector,
            "RETURN", count($returnFields) + 1, ...$returnFields, 'vector_score',
            "SORTBY", "vector_score", "DIALECT", "2"
        ]);
        // $rawDocs contains the results returned in the following format :
        // [
        //    0 => amount of results,
        //    1 => first result id,
        //    2 => [
        //       key1,
        //       value1,
        //       key2,
        //       valu2, 
        //       ...
        //    ],
        //    1 => second result id,
        //    2 => [
        //       key1,
        //       value1,
        //       key2,
        //       valu2,
        //       ...
        //    ],
        //    ...
        // ]
        $docs = [];
        for($x = 1; $x < count($rawDocs); $x += 2) {
            $doc = [];
            $currentDoc = $rawDocs[$x + 1];

            for ($y = 0; $y < count($currentDoc); $y +=2) {
                $doc[$currentDoc[$y]] = $currentDoc[$y + 1];
            }

            $docs[$rawDocs[$x]] = $doc;
        }

        return $docs;
    }
}
