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

This will start a Redis instance in your machine that will have everything you need for this project.

