<?php

namespace WP_Media_Folder\Aws\Api\Parser;

use Iterator;
use WP_Media_Folder\Aws\Exception\EventStreamDataException;
use WP_Media_Folder\Aws\Api\Parser\Exception\ParserException;
use WP_Media_Folder\Aws\Api\StructureShape;
use WP_Media_Folder\Psr\Http\Message\StreamInterface;
/**
 * @internal Implements a decoder for a binary encoded event stream that will
 * decode, validate, and provide individual events from the stream.
 */
class EventParsingIterator implements \Iterator
{
    /** @var StreamInterface */
    private $decodingIterator;
    /** @var StructureShape */
    private $shape;
    /** @var AbstractParser */
    private $parser;
    public function __construct(\WP_Media_Folder\Psr\Http\Message\StreamInterface $stream, \WP_Media_Folder\Aws\Api\StructureShape $shape, \WP_Media_Folder\Aws\Api\Parser\AbstractParser $parser)
    {
        $this->decodingIterator = new \WP_Media_Folder\Aws\Api\Parser\DecodingEventStreamIterator($stream);
        $this->shape = $shape;
        $this->parser = $parser;
    }
    public function current()
    {
        return $this->parseEvent($this->decodingIterator->current());
    }
    public function key()
    {
        return $this->decodingIterator->key();
    }
    public function next()
    {
        $this->decodingIterator->next();
    }
    public function rewind()
    {
        $this->decodingIterator->rewind();
    }
    public function valid()
    {
        return $this->decodingIterator->valid();
    }
    private function parseEvent(array $event)
    {
        if (!empty($event['headers'][':message-type'])) {
            if ($event['headers'][':message-type'] === 'error') {
                return $this->parseError($event);
            }
            if ($event['headers'][':message-type'] !== 'event') {
                throw new \WP_Media_Folder\Aws\Api\Parser\Exception\ParserException('Failed to parse unknown message type.');
            }
        }
        if (empty($event['headers'][':event-type'])) {
            throw new \WP_Media_Folder\Aws\Api\Parser\Exception\ParserException('Failed to parse without event type.');
        }
        $eventShape = $this->shape->getMember($event['headers'][':event-type']);
        $parsedEvent = [];
        foreach ($eventShape['members'] as $shape => $details) {
            if (!empty($details['eventpayload'])) {
                $payloadShape = $eventShape->getMember($shape);
                if ($payloadShape['type'] === 'blob') {
                    $parsedEvent[$shape] = $event['payload'];
                } else {
                    $parsedEvent[$shape] = $this->parser->parseMemberFromStream($event['payload'], $payloadShape, null);
                }
            } else {
                $parsedEvent[$shape] = $event['headers'][$shape];
            }
        }
        return [$event['headers'][':event-type'] => $parsedEvent];
    }
    private function parseError(array $event)
    {
        throw new \WP_Media_Folder\Aws\Exception\EventStreamDataException($event['headers'][':error-code'], $event['headers'][':error-message']);
    }
}
