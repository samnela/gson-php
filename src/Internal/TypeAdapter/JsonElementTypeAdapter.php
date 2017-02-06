<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Gson\Internal\TypeAdapter;

use LogicException;
use Tebru\Gson\Element\JsonArray;
use Tebru\Gson\Element\JsonElement;
use Tebru\Gson\Element\JsonNull;
use Tebru\Gson\Element\JsonObject;
use Tebru\Gson\Element\JsonPrimitive;
use Tebru\Gson\Internal\JsonWritable;
use Tebru\Gson\JsonReadable;
use Tebru\Gson\JsonToken;
use Tebru\Gson\TypeAdapter;

/**
 * Class JsonElementTypeAdapter
 *
 * @author Nate Brunette <n@tebru.net>
 */
final class JsonElementTypeAdapter extends TypeAdapter
{
    /**
     * Read the next value, convert it to its type and return it
     *
     * @param JsonReadable $reader
     * @return mixed
     * @throws \LogicException If the token can not be handled
     */
    public function read(JsonReadable $reader): JsonElement
    {
        switch ($reader->peek()) {
            case JsonToken::BEGIN_OBJECT:
                $object = new JsonObject();
                $reader->beginObject();
                while ($reader->hasNext()) {
                    $name = $reader->nextName();
                    $object->add($name, $this->read($reader));
                }
                $reader->endObject();

                return $object;
            case JsonToken::BEGIN_ARRAY:
                $array = new JsonArray();
                $reader->beginArray();
                while ($reader->hasNext()) {
                    $array->addJsonElement($this->read($reader));
                }
                $reader->endArray();

                return $array;
            case JsonToken::STRING:
                return new JsonPrimitive($reader->nextString());
            case JsonToken::NUMBER:
                return new JsonPrimitive($reader->nextDouble());
            case JsonToken::BOOLEAN:
                return new JsonPrimitive($reader->nextBoolean());
            case JsonToken::NULL:
                $reader->nextNull();

                return new JsonNull();
            default:
                throw new LogicException(sprintf('Could not handle token "%s"', $reader->peek()));
        }
    }

    /**
     * Write the value to the writer for the type
     *
     * @param JsonWritable $writer
     * @param mixed $value
     * @return void
     */
    public function write(JsonWritable $writer, $value): void
    {
    }
}