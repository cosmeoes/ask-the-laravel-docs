<?php

namespace CosmeDev\AskDocs;

use OpenAI;
use OpenAI\Client;

class AskDocs
{
    public Client $openai;
    public RedisHelper $redis;

    public function __construct()
    {
        $this->openai = OpenAI::client(config('openai.api_key'));
        $this->redis = new RedisHelper();
    }

    public function start()
    {
        if (!$this->redis->hasIndex(config('redis.index_name'))) {
            echo "Index doesn't exist so we'll create it (this only happens one time)...\n";
            $docLoader = new DocumentationLoader($this->redis, $this->openai);
            $docLoader->loadDocs();
        }

        $chatHistroy = [];
        while (true) {
            $question = readline("Type your question ('quit' to stop): ");
            if (trim($question) == 'quit') {
                exit();
            }

            $question = $this->questionWithHistory($question, $chatHistroy);
            $chatHistroy[] = 'User: ' . $question;
            $relevantDocs = $this->relevantDocs($question);
            $questionWithContext = $this->questionWithContext($question, array_column($relevantDocs, 'text'));
            $response = $this->chat($questionWithContext);
            $chatHistroy[] = 'Assistant: ' . $question;
            $this->showResponse($response, array_column($relevantDocs, 'url'));
        }
    }

    // We ask OpenAI to create a question that contains the whole context of the 
    // conversation so we can send a single question with the context and give
    // the illusion that it remembers the conversation history
    public function questionWithHistory($question, $chatHistory)
    {
        if (! $chatHistory) {
            return $question;
        }

        $template = <<<'TEXT'
Given the following conversation and a follow up question, rephrase the follow up question to be a standalone question.

Chat History:
{chat_history}
Follow Up Input: {question}
Standalone question:
TEXT;

        $template = str_replace('{question}', $question, $template);
        $template = str_replace('{chat_history}', implode("\n", $chatHistory), $template);

        return $this->chat($template);
    }

    public function chat($message)
    {
        $response = $this->openai->chat()->create([
            'model' => config('openai.completions_model'),
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
        ]);

        return $response->choices[0]->message->content;
    }

    // We convert the question into the embedding representation using OpenAI
    // embeddings endpoint.
    // We then use those embeddings to get the 4 most semantically similar documentation sections,
    //
    // We will send those sections as context to OpenAI so it can answer the question better.
    public function relevantDocs($question)
    {
        $result = $this->openai->embeddings()->create([
            'input' => $question,
            'model' => config('openai.embeddings_model')
        ]);

        // Encode the embeddings into a byte string. See helpers.php
        $packed = encode($result->embeddings[0]->embedding);
        return $this->redis->vectorSearch(config('redis.index_name'), 4, $packed, vectorName: "content_vector", returnFields: ['text', 'url']);
    }

    // We build the propmt with the context (the previously retrieved documentation sections)
    public function questionWithContext($question, $context)
    {
        $template = <<<'TEXT'
Answer the question based on the context below. With code samples if possible.

Context:
{context}

question: {question}
TEXT;

        $template = str_replace('{question}', $question, $template);
        $template = str_replace('{context}', implode("\n", $context), $template);

        return $template;
    }

    public function showResponse($response, $sources)
    {
        // The weird \e stuff is to add colors to the terminal output :)
        echo "\e[42m\e[30mOpenAI:\e[0m\e[32m " . $response . "\n\e[0m\e[43m\e[30mSources:\e[0m\e[96m\n" . implode("\n", $sources) . "\e[0m\n\n";
    }
}
