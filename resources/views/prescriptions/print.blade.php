<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription - {{ $prescription->patient->full_name }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            width: 210mm;
            height: 297mm;
            position: relative;
            background-color: white;
            box-sizing: border-box;
        }
        .background-template {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            /* If a template is uploaded, it is applied here */
        }
        .content {
            padding: 50mm 20mm 20mm 20mm; /* Adjust top padding if there's a letterhead header */
            z-index: 10;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .patient-info, .doctor-info {
            margin-bottom: 20px;
            width: 100%;
            border-collapse: collapse;
        }
        .patient-info td, .doctor-info td {
            padding: 5px;
            font-size: 14px;
            color: #555;
        }
        .rx-symbol {
            font-size: 48px;
            font-weight: bold;
            font-family: serif;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .medication-details {
            margin-top: 20px;
            font-size: 16px;
        }
        .medication-details p {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        .medication-details strong {
            color: #333;
        }
        .signature-area {
            margin-top: 50px;
            text-align: right;
            padding-right: 50px;
        }
        .signature-line {
            display: inline-block;
            width: 200px;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
        }
        
        /* Print Styles */
        @media print {
            body {
                width: 100%;
                height: 100%;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .no-print {
                display: none;
            }
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            z-index: 1000;
        }
        .print-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    @php
        $practice = \App\Models\Practice::find(1);
        $templateUrl = $practice && $practice->prescription_template 
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($practice->prescription_template)
            : null;
    @endphp

    @if($templateUrl)
        <img src="{{ $templateUrl }}" class="background-template" alt="Letterhead Template">
    @endif

    <button onclick="window.print()" class="print-btn no-print">Print Prescription</button>

    <div class="content">
        @if(!$templateUrl)
            <!-- Fallback Header if no template is uploaded -->
            <div class="header">
                <h1>{{ $practice->name ?? 'Dental Clinic' }}</h1>
                <p>Prescription Document</p>
            </div>
        @endif

        <table class="patient-info">
            <tr>
                <td style="width: 50%;"><strong>Patient Name:</strong> {{ $prescription->patient->full_name }}</td>
                <td style="width: 50%; text-align: right;"><strong>Date:</strong> {{ $prescription->date_prescribed ? $prescription->date_prescribed->format('d M, Y') : now()->format('d M, Y') }}</td>
            </tr>
            <tr>
                <td><strong>Age/Gender:</strong> {{ $prescription->patient->date_of_birth ? \Carbon\Carbon::parse($prescription->patient->date_of_birth)->age . ' yrs' : 'N/A' }} / {{ ucfirst($prescription->patient->gender ?? 'N/A') }}</td>
                <td style="text-align: right;"><strong>Phone:</strong> {{ $prescription->patient->phone ?? 'N/A' }}</td>
            </tr>
        </table>

        <hr style="border-top: 1px solid #ccc; margin: 20px 0;">

        <div class="rx-symbol">℞</div>

        <div class="medication-details">
            <p><strong>Medication:</strong> {{ $prescription->medication_name }}</p>
            <p><strong>Dosage:</strong> {{ $prescription->dosage }}</p>
            <p><strong>Frequency:</strong> {{ $prescription->frequency }}</p>
            <p><strong>Duration:</strong> {{ $prescription->duration }}</p>
            @if($prescription->instructions)
                <p><strong>Instructions:</strong> {{ $prescription->instructions }}</p>
            @endif
        </div>

        <div class="signature-area">
            <div class="signature-line"></div>
            <p><strong>Dr. {{ $prescription->doctor->name ?? 'Doctor' }}</strong></p>
        </div>
    </div>
    
    <!-- Auto-trigger print dialog -->
    <script>
        window.onload = function() {
            // Optional: Uncomment below to automatically open the print dialog when the page loads
            // window.print();
        };
    </script>
</body>
</html>
