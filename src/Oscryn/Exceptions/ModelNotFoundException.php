<?php

namespace Oscryn\Exceptions;

class ModelNotFoundException extends HttpException
{
    public function __construct(string $model = '', mixed $ids = null)
    {
        $message = 'No query results for model'.($model !== '' ? ' ['.$model.']' : '');

        if ($ids !== null) {
            $message .= ' '.implode(', ', array_map('strval', (array) $ids));
        }

        parent::__construct(404, $message);
    }
}
