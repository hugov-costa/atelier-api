<?php
/**
 * @var array{
 *     student_name: string,
 *     enrollment_number: string|null,
 *     reference_month: string,
 *     due_date: string,
 *     tuition_amount: int|null,
 *     pieces: array<int, array{name: string, materials: array<int, array{label: string, quantity: float, unit: string, unit_price: int, cost: int}>, firing: array<int, array{label: string, price: int}>, total: int}>,
 *     total: int,
 *     logo_data_uri: string|null
 * } $s
 */
$brl = static fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');
$qty = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
$due = \Illuminate\Support\Carbon::parse($s['due_date'])->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2933; font-size: 12px; margin: 0; padding: 32px; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .logo { max-height: 72px; max-width: 200px; }
        .title { font-size: 22px; font-weight: bold; color: #111; }
        .subtitle { color: #52606d; font-size: 12px; }
        .meta { width: 100%; margin-bottom: 20px; }
        .meta td { padding: 2px 0; }
        .meta .label { color: #52606d; width: 140px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.lines th { text-align: left; background: #f5f7fa; border-bottom: 2px solid #d9e2ec; padding: 8px; font-size: 11px; text-transform: uppercase; color: #52606d; }
        table.lines td { padding: 8px; border-bottom: 1px solid #e4e7eb; }
        .num { text-align: right; }
        .piece-name { font-weight: bold; padding-top: 12px; }
        .item-label { padding-left: 20px; color: #3e4c59; }
        .piece-total td { font-weight: bold; border-bottom: 2px solid #d9e2ec; }
        .grand-total { width: 100%; margin-top: 12px; }
        .grand-total td { padding: 10px 8px; font-size: 15px; font-weight: bold; }
        .grand-total .num { border-top: 2px solid #111; }
        .footer { margin-top: 32px; color: #829ab1; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if(! empty($s['logo_data_uri']))
                    <img class="logo" src="{{ $s['logo_data_uri'] }}" alt="Atelier">
                @endif
            </td>
            <td style="text-align: right;">
                <div class="title">Cobrança</div>
                <div class="subtitle">Referência: {{ $s['reference_month'] }}</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">Aluno(a)</td>
            <td>{{ $s['student_name'] }}</td>
        </tr>
        @if(! empty($s['enrollment_number']))
        <tr>
            <td class="label">Matrícula</td>
            <td>{{ $s['enrollment_number'] }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Vencimento</td>
            <td>{{ $due }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="num">Qtd.</th>
                <th class="num">Valor unit.</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @if($s['tuition_amount'] !== null)
            <tr>
                <td>Mensalidade</td>
                <td class="num">&mdash;</td>
                <td class="num">&mdash;</td>
                <td class="num">{{ $brl($s['tuition_amount']) }}</td>
            </tr>
            @endif

            @foreach($s['pieces'] as $piece)
            <tr>
                <td class="piece-name" colspan="4">Peça: {{ $piece['name'] }}</td>
            </tr>
            @foreach($piece['materials'] as $material)
            <tr>
                <td class="item-label">{{ $material['label'] }}</td>
                <td class="num">{{ $qty($material['quantity']) }} {{ $material['unit'] }}</td>
                <td class="num">{{ $brl($material['unit_price']) }}</td>
                <td class="num">{{ $brl($material['cost']) }}</td>
            </tr>
            @endforeach
            @foreach($piece['firing'] as $firing)
            <tr>
                <td class="item-label">Queima: {{ $firing['label'] }}</td>
                <td class="num">&mdash;</td>
                <td class="num">&mdash;</td>
                <td class="num">{{ $brl($firing['price']) }}</td>
            </tr>
            @endforeach
            <tr class="piece-total">
                <td colspan="3">Subtotal da peça</td>
                <td class="num">{{ $brl($piece['total']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="grand-total">
        <tr>
            <td>Total a pagar</td>
            <td class="num">{{ $brl($s['total']) }}</td>
        </tr>
    </table>

    <div class="footer">Documento gerado automaticamente pelo atelier.</div>
</body>
</html>
