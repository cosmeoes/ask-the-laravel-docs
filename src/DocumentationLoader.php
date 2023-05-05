<?php
namespace CosmeDev\AskDocs;

use MacFJA\RediSearch\IndexBuilder;
use MacFJA\RediSearch\Redis\Command\CreateCommand\VectorFieldOption;
use OpenAI\Client;
use ZipArchive;
use TerminalProgress\Bar;

class DocumentationLoader
{
    public function __construct(public RedisHelper $redis, public Client $openai)
    {
    }

    public function loadDocs()
    {
        $builder = new IndexBuilder();
        $builder
            ->setIndex(config('redis.index_name'))
            ->setPrefixes(['doc'])
            ->addTextField('text')
            ->addTextField('url')
            // This is the field used to get the relevant documentation sections.
            ->addVectorField(
                'content_vector',
                VectorFieldOption::ALGORITHM_FLAT,
                VectorFieldOption::TYPE_FLOAT32,
                // OpenAI embeddings size, as in the embedding endpoint returns
                // an array of 1536 items.
                1536,
                VectorFieldOption::DISTANCE_METRIC_COSINE
            );

        $this->redis->createIndex($builder);

        $this->batchLoad();
    }

    // We iterate over every file in the documentation
    // and we split them in chunks, each chunk represents a 
    // section of the documentation.
    //
    // it returns an array of documentation section with "text"
    // and "urls"
    public function parseDocs($path)
    {
        echo "Parsing doc files...\n";
        $files = scandir($path);
        $docs = [];
        $base_url = "https://laravel.com/docs/10.x/";
        foreach ($files as $file) {
            if (!str_ends_with($file, '.md')) {
                continue;
            }

            $url = $base_url . explode('.', $file)[0];
            $fullPath = rtrim($path, '/') . '/' . $file;
            $contents = file_get_contents($fullPath);
            preg_match_all("/name=\"(?<link>.+?)\"><\/a>\n(?<text>#.+?)(?:<a|\z)/s", $contents, $matches, PREG_SET_ORDER);

            $sections = array_map(fn ($match) => [
                'url' => "{$url}#{$match['link']}",
                'text' => $match['text'],
            ], $matches);

            $docs = array_merge($docs, $sections);
        }

        return $docs;
    }

    // Download the documentation from github
    // and unzip it into the given path
    public function downloadDocs($path)
    {
        echo "Downloading and unzipping docs...\n";
        $content = file_get_contents('https://github.com/laravel/docs/archive/refs/heads/10.x.zip');
        $file = fopen('./temp.zip', 'w+');
        fwrite($file, $content);
        fclose($file);
        $zip = new ZipArchive();
        $zip->open('./temp.zip');
        $zip->extractTo($path);
        $zip->close();
        unlink('./temp.zip');
    }

    // We split the documentation sections into batches 
    // of 10 sections at a time, this is to send 10 sections at the
    // same time to OpenAI to get the embeddings for the text
    // we then put those embeddings into the 'content_vector' 
    // field and store the section in redis
    public function batchLoad()
    {
        $this->downloadDocs(config('docs_path'));
        $docs = $this->parseDocs(config('docs_path') . '/docs-10.x');
        $batchSize = 10;
        $currentKeyCount = 0;
        echo "Storing documents in redis...\n";
        $progressBar = new Bar(count($docs));
        foreach (array_chunk($docs, $batchSize) as $chunk) {
            // Convert the text from each section into the embeddings 
            // representation using the OpenAI embedding endpoint
            $texts = array_column($chunk, 'text');
            $response = $this->openai->embeddings()->create([
                'input' => $texts,
                'model' => config('openai.embeddings_model')
            ]);

            foreach ($response->embeddings as $index => $embeddingsResponse) {
                // We have to store the embeddings as bytes in redis so we convert
                // them to a byte string using the encode() function. See helpers.php
                $packed = encode($embeddingsResponse->embedding);
                $chunk[$index]['content_vector'] = $packed;
                $this->redis->hset("doc:{$currentKeyCount}", $chunk[$index]);
                $currentKeyCount++;
                $progressBar->tick();
            }
        }
    }
}
