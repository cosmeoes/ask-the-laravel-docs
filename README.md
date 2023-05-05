# Ask The Laravel Docs

After I released a section on my blog where you can ask the Laravel docs questions (https://cosme.dev/ask-docs) using OpenAI's API and the Laravel documentation I got a lot of messages asking me how did I do it and questions about how they can add something similar to their existing projects.

So I decided to make this open source repo showing how to do something similar in pure PHP (the original website implementation on my website was made with a combination of php, python and golang).

If you just want to test the projects simply follow the installations instructions below. If you are looking to understand better how it works I will add an explanation below of the concepts as I understand them (I'm still learning this stuff) or you can just look at the code, I added some comments that hopefully will make it easier to understand what is going on.

## Installation

### Requirements

To run this project on your computer you need to have PHP and composer installed. 

You also need an OpenAI API Key, you can get one here: https://platform.openai.com/account/api-keys

I think you also need to have a billing method added to your OpenAI account. This project will generate spent in your account, the first time you run it, it will generate embeddings for all the Laravel docs, but that won't cost more than $0.20 or something along those lines. Asking questions are a little more expensive since it uses the completions API and we send context on each question but overall you should be able to test and play with this repo for less than $1. For more information about OpenAI pricing visit this link: https://openai.com/pricing#language-models

You also need to have a Redis instance with the [RediSearch](https://redis.io/docs/stack/search/) extension running, the easiest to do this is by using the official docker image:

```
docker run -d --name redis-stack-server -p 6379:6379 redis/redis-stack-server:latest
```

This will start a Redis instance in your machine that will have everything you need for this project. If you don't have docker installed on your computer, follow the installation instructions for your OS: https://docs.docker.com/get-docker/

### Running the project

First clone the github repo:
```
git clone git@github.com:cosmeoes/ask-the-laravel-docs.git
```

Install the dependencies:
```
composer install
```

Copy the .env.example file to .env:

```
cp .env.example .env
```

Add your OpenAI API key to the .env file:
```
OPENAI_API_KEY={YOUR_API_KEY}
```

If you haven't already start the Redis docker container:
```
docker run -d --name redis-stack-server -p 6379:6379 redis/redis-stack-server:latest
```

Note: If you want to connect to a Redis server that is not running own your local machine or it's running on another port, you can change the connection configuration in the `config.php` file.

Run the project:
```
php ask-docs.php
```

This will download the documentation to `docs/` and start indexing them into the Redis instance, you'll see a progress bar and the estimated remaining time.

Once it finishes you'll be presented with this prompt:
```
Type your question ('quit' to stop):
```

You can type any question and start using the tool :)


# How does it work?

The way this works might seem complicated at first but in reality is a very simple process. There are however some concepts that you need to know to understand better the way this project was made and how you can adapt it to your own projects:

- Embeddings:

   From the OpenAI docs: "An embedding is a vector (list) of floating point numbers. The distance between two vectors measures their relatedness. Small distances suggest high relatedness and large distances suggest low relatedness." 

   In more simple terms they are an array of numbers that represent text. We can use these numbers to calculate how related two or more pieces of texts are.

- Vector Database:

    A vector database sounds complicated but in reality the concept is very simple. We use vector databases to store the embeddings of text, once we have those embeddings in the vector database we can make a query to get the most related texts from the database. For example, in this project we create a vector database that contains all the embeddings for the Laravel documentation, then when you ask a question via the prompt we generate the embeddings for that question and then query the database to get the 4 most relevant results. So it works in a similar way to a full text search, except we can use them to match by how similar the meaning of the two text are instead of keywords.

    In this project I decided to use Redis as the vector database, but there are many other options including a vector extension for PostgreSQL, so don't think you need to use Redis to create something similar.

## Step by step break down

First let's start on how I insert the Laravel docs into the vector database:

1. The first step is the index definition. I added three fields to the index: 
    1. "text" for the text of that documentation section.
    2. "url" to show the "Sources" list after the OpenAI response.
    3. "content_vector" this is a vector field that will contain the embedding for each section of the docs. This is the field that we will query against.

2. The second step is breaking the database into smaller chunks. The reason for this is because the OpenAI API has a character limit when we make a request so we can't send the whole documentation at the same time.

    The way I decided to do this is by breaking it into each documentation section. In other words, every time there is a header or subheader in the docs I split it. In this step I also build the url for that section so we can display them as the "Sources:" links after we show the OpenAI response.

3. The third step is converting each one of those sections into embeddings and storing them into the index. We get the embeddings sending the text to the embeddings endpoint of the OpenAI API. It returns an array of floating point numbers. I send 10 sections at a time to the OpenAI API to make it faster. I choose 10 because I saw that in an example and it worked for this use case. But its not a set number and you can experiment with it.

    To store this in redis you have to convert them to a byte string so I do that using the `pack()` function. (See the `helpers.php` file). Then I put those bytes into `content_vector` field on each documentation section item and store them with `hset` and the key `doc:{index}` where `index` is just the index of the iteration. This is pretty specific to redis, so if you use another vector database you'll probably have to follow the documentation to know how you should store them.

After we have the vector database with our documents in them we can start asking questions, the question/response flow is the following:

1. When a user inputs text we send that text to OpenAI embeddings endpoint to generate the embeddings of that text.
2. With those embeddings we query the database to get the 4 most similar documents (as in semantically similar not text search similar).
3. We take the text from those documents and create a string that contains both the documentation text and the question (you can see the [code here](https://github.com/cosmeoes/ask-the-laravel-docs/blob/c4b7891bdcb8dc07c72535964ae758270be1a7bb/src/AskDocs.php#L98-L113))
4. We send that text to OpenAI chat endpoint, in this case we use `gpt-3.5-turbo` as the model.

    That is the basic flow for the first question. But if you ask multiple questions you will notice that the chat "remembers" what you typed previously, this is not a feature of the OpenAI API and instead is done by storing the question and responses in an array and asking OpenAI to generate a stand alone question (you can see the [code here](https://github.com/cosmeoes/ask-the-laravel-docs/blob/c4b7891bdcb8dc07c72535964ae758270be1a7bb/src/AskDocs.php#L47-L66)). We then use that question to query the vector database and use that to get OpenAI's response.


And that's it, that's the gist of how it all works, you probably should read this as you read the code and it will all make more sense, if you have any other questions you can ask me on tweeter [@cosmeescobedo](https://twitter.com/cosmeescobedo) or send me an email at cosme@cosme.dev


