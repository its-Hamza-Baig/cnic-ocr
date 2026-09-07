<?php

namespace App\Logging;

use App\Support\SensitiveData;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveDataProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: SensitiveData::redact($record->message),
            context: SensitiveData::redactArray($record->context),
            extra: SensitiveData::redactArray($record->extra),
        );
    }
}
