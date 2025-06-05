<!-- resources/views/zlecenia/drukuj.blade.php -->
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zlecenie produkcyjne - {{ $zlecenie->numer }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
        }
        .info-value {
            margin-left: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }
        .signature-box {
            width: 40%;
            border-top: 1px solid #333;
            padding-top: 10px;
            text-align: center;
        }
        @media print {
            body {
                padding: 0;
                font-size: 12px;
            }
            .container {
                max-width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">ZLECENIE PRODUKCYJNE</div>
            <div class="subtitle">{{ $zlecenie->numer }}</div>
        </div>
        
        <div class="info-section">
            <div class="section-title">Informacje podstawowe</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Data zlecenia:</span>
                    <span class="info-value">{{ $zlecenie->data_zlecenia->format('d.m.Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        @switch($zlecenie->status)
                            @case('nowe')
                                Nowe
                                @break
                            @case('w_realizacji')
                                W realizacji
                                @break
                            @case('zrealizowane')
                                Zrealizowane
                                @break
                            @case('anulowane')
                                Anulowane
                                @break
                            @default
                                {{ $zlecenie->status }}
                        @endswitch
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Planowana realizacja:</span>
                    <span class="info-value">{{ $zlecenie->planowana_data_realizacji ? $zlecenie->planowana_data_realizacji->format('d.m.Y') : 'Nie określono' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ilość:</span>
                    <span class="info-value">{{ $zlecenie->ilosc }} szt.</span>
                </div>
            </div>
        </div>
        
        <div class="info-section">
            <div class="section-title">Produkt</div>
            @if($zlecenie->produkt)
                <div class="info-grid">
                    <div class="info-row">
                        <span class="info-label">Nazwa:</span>
                        <span class="info-value">{{ $zlecenie->produkt->nazwa }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Kod:</span>
                        <span class="info-value">{{ $zlecenie->produkt->kod }}</span>
                    </div>
                    @if($zlecenie->produkt->receptura)
                        <div class="info-row">
                            <span class="info-label">Receptura:</span>
                            <span class="info-value">{{ $zlecenie->produkt->receptura->nazwa }}</span>
                        </div>
                    @endif
                    @if($zlecenie->produkt->opakowanie)
                        <div class="info-row">
                            <span class="info-label">Opakowanie:</span>
                            <span class="info-value">{{ $zlecenie->produkt->opakowanie->nazwa }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pojemność:</span>
                            <span class="info-value">{{ number_format($zlecenie->produkt->opakowanie->pojemnosc, 0) }} g</span>
                        </div>
                    @endif
                </div>
            @else
                <p>Brak danych o produkcie.</p>
            @endif
        </div>
        
        <div class="info-section">
            <div class="section-title">Surowce potrzebne do realizacji zlecenia</div>
            @if(!empty($zlecenie->surowce_potrzebne))
                <table>
                    <thead>
                        <tr>
                            <th>Lp.</th>
                            <th>Nazwa</th>
                            <th>Kod</th>
                            <th>Ilość</th>
                            <th>Cena jedn.</th>
                            <th>Koszt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $suma = 0; $lp = 1; @endphp
                        @foreach($zlecenie->surowce_potrzebne as $surowiec)
                            <tr>
                                <td>{{ $lp++ }}</td>
                                <td>{{ $surowiec['nazwa'] }}</td>
                                <td>{{ $surowiec['kod'] }}</td>
                                <td>{{ number_format($surowiec['ilosc']) }} {{ $surowiec['jednostka'] }}</td>
                                <td>{{ number_format($surowiec['cena_jednostkowa'], 2) }} PLN</td>
                                <td>{{ number_format($surowiec['koszt'], 2) }} PLN</td>
                            </tr>
                            @php $suma += $surowiec['koszt']; @endphp
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; font-weight: bold;">Suma:</td>
                            <td style="font-weight: bold;">{{ number_format($suma, 2) }} PLN</td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <p>Brak danych o potrzebnych surowcach.</p>
            @endif
        </div>
        
        @if($zlecenie->uwagi)
            <div class="info-section">
                <div class="section-title">Uwagi</div>
                <p>{{ $zlecenie->uwagi }}</p>
            </div>
        @endif
        
        <div class="signature-section">
            <div class="signature-box">
                Zlecił
            </div>
            <div class="signature-box">
                Przyjął do realizacji
            </div>
        </div>
        
        <div class="footer">
            <p>Dokument wygenerowany dnia {{ now()->format('d.m.Y H:i') }}</p>
        </div>
        
        <div class="no-print" style="margin-top: 20px; text-align: center;">
            <button onclick="window.print()">Drukuj dokument</button>
            <button onclick="window.close()">Zamknij</button>
        </div>
    </div>
</body>
</html>