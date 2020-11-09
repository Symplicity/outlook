<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Batch;

use Symplicity\Outlook\Interfaces\Entity\BatchWriterEntityInterface;

interface FormatterInterface
{
    public function format(BatchWriterEntityInterface $writer): array;
}
