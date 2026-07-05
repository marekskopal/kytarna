<?php

declare(strict_types=1);

namespace Kytario\Model\Entity\Enum;

enum RecurrenceCadenceEnum: string
{
	case Daily = 'Daily';
	case Weekly = 'Weekly';
	case Monthly = 'Monthly';
	case Cron = 'Cron';
}
