<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a material purchase (or other atelier payment) was settled.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Pix = 'pix';
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case BankSlip = 'bank_slip';
    case BankTransfer = 'bank_transfer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $method): string => $method->value, self::cases());
    }
}
