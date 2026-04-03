<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hire Agreement - {{ $driver->full_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .top-line {
            border-top: 2px solid #000;
            text-align: center;
            font-weight: bold;
            padding: 5px 0;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            background-color: yellow;
            padding: 3px;
            margin: 5px 0;
            display: inline-block;
        }

        .company-email {
            font-size: 12px;
            font-weight: bold;
            background-color: yellow;
            padding: 3px;
            margin: 5px 0;
            display: inline-block;
        }

        .section-header {
            background-color: yellow;
            font-weight: bold;
            padding: 3px;
            margin: 15px 0 10px 0;
            display: inline-block;
        }

        .details-row {
            margin: 8px 0;
            display: table;
            width: 100%;
        }

        .details-item {
            display: table-cell;
            padding-right: 20px;
            vertical-align: top;
        }

        .field-label {
            font-weight: bold;
        }

        .field-value {
            border-bottom: 1px solid #000;
            min-height: 16px;
            padding-bottom: 2px;
            display: inline-block;
            min-width: 150px;
        }

        .conditions-header {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            text-align: center;
            font-weight: bold;
            padding: 10px;
            margin: 20px 0;
        }

        .conditions {
            margin: 15px 0;
        }

        .conditions ol {
            padding-left: 20px;
        }

        .conditions li {
            margin-bottom: 8px;
            text-align: justify;
        }

        .highlight {
            font-weight: bold;
        }

        .vehicle-condition {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            text-align: center;
            font-weight: bold;
            padding: 10px;
            margin: 20px 0;
        }

        .final-note {
            text-align: center;
            font-weight: bold;
            margin: 15px 0;
        }

        .signature-section {
            margin-top: 30px;
            text-align: center;
        }

        .signature-line {
            display: inline-block;
            width: 30%;
            text-align: center;
            margin: 0 1.5%;
        }

        .signature-text {
            border-bottom: 1px solid #000;
            height: 30px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<!-- Header Section -->
<div class="top-line">
    Hire Agreement
</div>
<div class="top-line"></div>

<div class="header">
    <div class="company-name">{{ $company->name ?? 'SAMORE TRADERS LTD' }}</div>
    <br>
{{--
    <div class="company-email">{{ $company->email ?? 'samoretradersltd@gmail.com' }}</div>
--}}
</div>

<!-- Details Section - Two Columns -->
<div style="display: table; width: 100%; margin-bottom: 20px;">
    <!-- Left Column - Customer Details -->
    <div style="display: table-cell; width: 50%; vertical-align: top; padding-right: 20px;">
        <div class="section-header">Customer Details</div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Name:</span><br>
                <span class="field-value">{{ $driver->full_name }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Licence N.O:</span><br>
                <span class="field-value">{{ $driver->driver_license_number ?? '' }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Expires:</span><br>
                <span
                    class="field-value">{{ $driver->driver_license_expiry_date ? \Carbon\Carbon::parse($driver->driver_license_expiry_date)->format('d/m/Y') : '' }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">PHD Licence No:</span><br>
                <span class="field-value">{{ $driver->phd_license_number ?? '' }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">DOB:</span><br>
                <span
                    class="field-value">{{ $driver->dob ? \Carbon\Carbon::parse($driver->dob)->format('d/m/Y') : '' }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Phone N.O:</span><br>
                <span class="field-value">{{ $driver->phone_number ?? '' }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Address:</span><br>
                <span class="field-value">{{ $driver->address1 ?? '' }}</span>
            </div>
        </div>
    </div>

    <!-- Right Column - Vehicle Details -->
    <div style="display: table-cell; width: 50%; vertical-align: top; padding-left: 20px;">
        <div class="section-header">Vehicle Details</div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Make & Model:</span><br>
                <span class="field-value">{{ $car->carModel->name ?? '' }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Vehicle REG:</span><br>
                <span class="field-value">{{ $car->registration }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Mileage Out:</span><br>
                <span class="field-value">{{ number_format($agreement->mileage_out ?? 0) }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Date/Time Out:</span><br>
                <span class="field-value">{{ $agreement->start_date->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Date/Time Due:</span><br>
                <span class="field-value">{{ $agreement->end_date->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Hire Charge:</span><br>
                <span class="field-value">£{{ number_format($agreement->agreed_rent, 2) }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Deposit:</span><br>
                <span class="field-value">£{{ number_format($agreement->deposit_amount, 2) }}</span>
            </div>
        </div>

        <div class="details-row">
            <div class="details-item">
                <span class="field-label">Vehicle return date:</span><br>
                <span class="field-value">{{ $agreement->end_date->format('d/m/Y') }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Conditions Header -->
<div class="conditions-header">
    Conditions of Hire Agreement: MIN 4 WEEKS CONTRACT £1000 ACCESS IN CASE OF ACCIDENT
</div>

<!-- Conditions List -->
<div class="conditions">
    <ol>
        <li>I am responsible for insuring the vehicle.</li>

        <li>I am over 21 and less than 86 years of age, suffer from no physical or mental impairment affecting my
            ability to drive, and have held a valid UK licence applicable to the vehicle for at least 12 months. I have
            not accumulated more than 9 penalty points in the last 3 years, nor have I been disqualified from driving in
            the last 5 years. I will pay any charges for the loss/damage as a result of not using the correct fuel <span
                class="highlight">(This is a {{ $car->fuel_type ?? 'Petrol' }} Vehicle)</span>.
        </li>

        <li>I will accept full responsibility for any uninsured loss or damage however such loss or damage is caused. In
            the event of an accident I will report the incident immediately
            to {{ $company->name ?? 'SAMORE TRADERS LTD' }} and will complete an accident report form
            with {{ $company->name ?? 'SAMORE TRADERS LTD' }}. Any necessary repair work will be carried out
            by {{ $company->name ?? 'SAMORE TRADERS LTD' }} and paid by me upon receipt of the invoice.
        </li>

        <li>I accept that any motoring or traffic offences, toll charges, congestion charges, penalties and fines
            arising in relation to the vehicle for the duration of the loan are my sole responsibility under the Road
            Traffic Regulations Act 1984, the Road Traffic Offenders Act 1988, and/or any subsequent relevant
            legislation. I will indemnify {{ $company->name ?? 'SAMORE TRADERS LTD' }} forthwith for any penalties,
            fines, legal fees, costs, interest or other charges paid by them in relation to any such motoring or traffic
            offences or toll charges. I hereby irrevocably consent to any such charges including an administration fee
            of £35 being charged to me.
        </li>

        <li>I agree the vehicle is only insured to be driven by the person(s) named above who have signed this form and
            will only be used for social, domestic and pleasure including commuting by the insured to a permanent place
            of work. For the carriage of passengers or goods for hire and reward by prior appointment only provided
            (private hire) such use complies with the laws and regulations of the appropriate licensing authority. I
            will indemnify {{ $company->name ?? 'SAMORE TRADERS LTD' }} in full against any and all claims, costs and
            expenses arising out of the driving of the vehicle by any other persons.
        </li>

        <li>I will not enter into any agreement with any third party for further hire or loan of the vehicle.</li>

        <li>I understand that the vehicle must not be modified in any way.</li>

        <li>I understand it is my responsibility to check oil and water during the hire period. Failure to do so will
            result in charges.
        </li>

        <li>If any radio equipment is installed it must not be affixed to the vehicle i.e. screwed on brackets.</li>

        <li>If any insurance or third party pay out all payments must be made payable to
            "{{ $company->name ?? 'SAMORE TRADERS LTD' }}".
        </li>

        <li>The vehicle remains property of {{ $company->name ?? 'SAMORE TRADERS LTD' }} and can be taken back anytime
            without the permission of the hirer.
        </li>

        <li>The vehicle must be secured when parked and security features used correctly.</li>

        <li>If I fail to make rental, traffic offences or vehicle damage payments, I
            authorise {{ $company->name ?? 'SAMORE TRADERS LTD' }} or a sub-contractor to clamp and remove any vehicle,
            goods and chattels in my possession/custody at any time without notice to clear my debt. I understand I will
            incur further charges.
        </li>

        <li>I agree that if I am in breach of my contract then {{ $company->name ?? 'SAMORE TRADERS LTD' }} has a right
            of recovery against me.
        </li>

        <li>I also understand that this agreement can be terminated anytime by either me
            or {{ $company->name ?? 'SAMORE TRADERS LTD' }}.
        </li>

        <li>If car is returned before defined period then {{ $company->name ?? 'SAMORE TRADERS LTD' }} has the right to
            not refund the deposit.
        </li>
    </ol>
</div>

<div class="final-note">
    DEPOSITS WILL BE RETURNED WITHIN 4 WORKING DAYS WHEN THE HIRE VEHICLE IS RETURNED.
</div>

<!-- Vehicle Condition Section -->
<div class="vehicle-condition">
    Vehicle Condition
</div>

<div style="text-align: center; margin: 20px 0;">
    <p><strong>CHECK BEFORE TAKING VEHICLE IF ANY TYRES OR BULBS ARE REQUIRED, AS NONE WILL BE PROVIDED AT A LATER
            DATE.</strong></p>

    @if($agreement->condition_report)
        <p><strong>Condition Report:</strong> {{ $agreement->condition_report }}</p>
    @endif

    <p style="margin-top: 20px;">I have read and understand ALL of the above conditions of hire and I agree to abide by
        them. I agree with the above vehicle condition report and I agree the vehicle is ONLY insured to be driven by
        the person(s) named above who have signed this form.</p>
</div>

<!-- Signature Section -->
<div class="signature-section">
    <div class="signature-line">
        <div class="signature-text"></div>
        <strong>Client</strong>
    </div>
    <div class="signature-line">
        <div class="signature-text"></div>
        <strong>{{ $company->name ?? 'SAMORE TRADERS LTD' }}</strong>
    </div>
    <div class="signature-line">
        <div class="signature-text"></div>
        <strong>Date</strong>
    </div>
</div>

<div style="text-align: center; margin-top: 30px; font-size: 10px; color: #666;">
    Agreement ID: {{ $agreement->id }} | Generated on: {{ \Carbon\Carbon::now()->format('d/m/Y') }}
</div>

{{-- ===== PAGE BREAK - Statement of Understanding shuru ===== --}}
<div style="page-break-before: always; padding: 20px;">

    <div style="border-top: 3px solid #000; border-bottom: 3px solid #000; text-align: center; font-weight: bold; font-size: 14px; padding: 10px; margin-bottom: 20px; text-decoration: underline;">
        STATEMENT OF UNDERSTANDING
    </div>

    {{-- EXCESS FEE --}}
    <div style="margin-bottom: 15px;">
        <p style="font-weight: bold; margin-bottom: 5px;">EXCESS FEE:</p>
        <p style="text-align: justify; margin-left: 20px;">
            I HEREBY ACKNOWLEDGE AND AGREE THAT IN THE EVENT OF A MOTOR ACCIDENT, I AM RESPONSIBLE FOR PAYING THE
            APPLICABLE INSURANCE EXCESS FEE IN ORDER TO PROCEED WITH THE CLAIM PROCESS. IF, UPON CONCLUSION OF THE
            INVESTIGATION, THE ACCIDENT IS DETERMINED TO BE NON-FAULT, THE EXCESS FEE PAID BY ME SHALL BE REIMBURSED
            IN FULL. HOWEVER, IF THE OUTCOME OF THE CLAIM ESTABLISHES THAT I AM AT FAULT, I ACKNOWLEDGE THAT I SHALL
            HAVE NO RIGHT OR ENTITLEMENT TO RECOVER OR REQUEST REIMBURSEMENT OF THE EXCESS FEE, AS IT WILL BE DEEMED
            DULY PAYABLE. FURTHERMORE, I UNDERSTAND AND AGREE THAT IN CASES WHERE THE ACCIDENT INVOLVES AN UNINSURED
            THIRD PARTY, A STOLEN VEHICLE, OR IF THE VEHICLE I AM DRIVING IS STOLEN, I SHALL BE LIABLE TO PAY THE
            FULL EXCESS AMOUNT REGARDLESS OF FAULT, AS SUCH CIRCUMSTANCES MAY PREVENT RECOVERY OF COSTS FROM THE
            THIRD PARTY.
        </p>
    </div>

    {{-- TYRES & HEADLIGHT BULB --}}
    <div style="margin-bottom: 15px;">
        <p style="font-weight: bold; margin-bottom: 5px;">TYRES & HEADLIGHT BULB:</p>
        <p style="text-align: justify; margin-left: 20px;">
            I ACKNOWLEDGE THAT UPON RENTING OUT CAR I WILL CHECK AND AGREE TO THE CONDITION OF TYRES AND BULBS.
            ONCE TAKEN THE CAR, IT WILL BECOME MY RESPONSIBILITY TO CHANGE THE TYRES AND BULBS.
        </p>
    </div>

    {{-- CAR WASH --}}
    <div style="margin-bottom: 15px;">
        <p style="font-weight: bold; margin-bottom: 5px;">CAR WASH:</p>
        <p style="text-align: justify; margin-left: 20px;">
            I ACKNOWLEDGE THAT UPON RETURNING THE CAR BACK, I WILL MAKE SURE TO RETURN THE CAR WASHED AND VACUUMED
            AND IN IMMACULATE CONDITION OTHERWISE THE COMPANY RESERVE THE RIGHT TO CHARGE ME £30 POUNDS.
        </p>
    </div>

    {{-- NOTICE --}}
    <div style="margin-bottom: 15px;">
        <p style="font-weight: bold; margin-bottom: 5px;">NOTICE:</p>
        <p style="text-align: justify; margin-left: 20px;">
            I ACKNOWLEDGE THAT I AM BOUND TO GIVE ONE WEEK'S NOTICE AFTER MY MINIMUM CONTRACTUAL TERM HAS FINISHED
            IN ORDER TO RETURN THE VEHICLE. I FURTHER ACKNOWLEDGE THAT THIS NOTICE MUST BE GIVEN ON THE DUE DATE OF
            MY RENT PAYMENT; FAILURE TO DO SO WILL RESULT IN THE FORFEITURE OF MY DEPOSIT FOR CLOSING WITHOUT NOTICE.
            I ALSO UNDERSTAND AND ACCEPT THAT VEHICLE RENTALS ARE CHARGED ON A WEEKLY BASIS, NOT DAILY, AND ANY
            CLOSURE WILL BE CHARGED AS A FULL WEEK, REGARDLESS OF THE NUMBER OF DAYS REMAINING.
        </p>
    </div>

    {{-- MOT AND PLATES --}}
    <div style="margin-bottom: 15px;">
        <p style="font-weight: bold; margin-bottom: 5px;">MOT AND PLATES APPOINTMENT:</p>
        <p style="text-align: justify; margin-left: 20px;">
            I ACKNOWLEDGE THAT WHENEVER THE VEHICLE REQUIRED MOT OR COUNCIL APPOINTMENT FOR RENEWAL OF PLATES,
            I AM BOUND TO BRING THE VEHICLE ON THE APPOINTMENT TIME TO THE OFFICE.
        </p>
    </div>

    {{-- DRIVING LICENCE CHANGES --}}
    <div style="margin-bottom: 20px;">
        <p style="font-weight: bold; margin-bottom: 5px;">DRIVING LICENCE CHANGES OR CONVICTIONS:</p>
        <p style="text-align: justify; margin-left: 20px;">
            I ACKNOWLEDGE THAT I AM <strong>OBLIGED TO IMMEDIATELY INFORM THE COMPANY</strong> OF ANY <strong>CHANGES</strong>
            TO MY DRIVING LICENCE STATUS, INCLUDING BUT NOT LIMITED TO:
        </p>
        <ul style="margin-left: 40px; text-align: justify;">
            <li style="margin-bottom: 5px;">
                <strong>ENDORSEMENTS, PENALTY POINTS, DISQUALIFICATIONS, OR DRIVING CONVICTIONS</strong> OF ANY KIND.
            </li>
            <li style="margin-bottom: 5px;">
                <strong>SUSPENSION, REVOCATION, OR RESTRICTION</strong> OF MY DRIVING LICENCE.
                FAILURE TO NOTIFY THE COMPANY OF SUCH CHANGES WILL RESULT IN
                <strong>IMMEDIATE TERMINATION OF THIS AGREEMENT AND THE RENTAL CONTRACT</strong>,
                AND I MAY BE REQUIRED TO <strong>RETURN THE VEHICLE WITHOUT NOTICE</strong>.
            </li>
        </ul>
    </div>

    {{-- SOU - Driver Details Fields --}}
    <div style="margin-top: 20px; border-top: 1px solid #000; padding-top: 15px;">
        <table style="width: 100%; font-size: 11px;">
            <tr>
                <td style="width: 50%; padding: 5px 10px 5px 0;">
                    <strong>CAR REG:</strong>
                    <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 150px; padding-bottom: 2px;">
                        {{ $car->registration }}
                    </span>
                </td>
                <td style="width: 50%; padding: 5px 0 5px 10px;">
                    <strong>NAME: MR</strong>
                    <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 150px; padding-bottom: 2px;">
                        {{ $driver->full_name }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 10px 5px 0;">
                    <strong>DRIVING LICENCE:</strong>
                    <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 130px; padding-bottom: 2px;">
                        {{ $driver->driver_license_number ?? '' }}
                    </span>
                </td>
                <td style="padding: 8px 0 5px 10px;">
                    <strong>N.I NUMBER:</strong>
                    <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 150px; padding-bottom: 2px;">
                        {{ $driver->ni_number ?? '' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 8px 0 5px 0;">
                    <strong>EMAIL:</strong>
                    <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 250px; padding-bottom: 2px;">
                        {{ $driver->email ?? '' }}
                    </span>
                </td>
            </tr>
        </table>

        {{-- SOU Signature Line --}}
        <div style="margin-top: 25px; display: table; width: 100%;">
            <div style="display: table-cell; width: 60%; padding-right: 20px;">
                <strong>SIGN:</strong>
                <span style="border-bottom: 1px solid #000; display: inline-block; width: 70%; height: 30px;"></span>
            </div>
            <div style="display: table-cell; width: 40%;">
                <strong>DATE:</strong>
                <span style="border-bottom: 1px solid #000; display: inline-block; width: 70%; height: 30px;"></span>
            </div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 20px; font-size: 10px; color: #666;">
        Agreement ID: {{ $agreement->id }} | Statement of Understanding
    </div>
</div>

{{-- ===== PAGE BREAK - Statement of Liability shuru ===== --}}
<div style="page-break-before: always; padding: 20px;">

    <div style="border-top: 3px solid #000; border-bottom: 3px solid #000; text-align: center; font-weight: bold; font-size: 14px; padding: 10px; margin-bottom: 20px;">
        STATEMENT OF LIABILITY
    </div>

    <p style="text-align: justify; margin-bottom: 20px;">
        I ACCEPT THAT ANY MOTORING OR TRAFFIC OFFENCES, TOLL CHARGES, CONGESTION CHARGES, PENALTIES, ANY PARKING
        CHARGES, BUS LANE CONTRAVENTION AND FINE ARISING IN RELATION TO THE VEHICLE FOR THE DURATION OF THE LOAN
        ARE MY SOLE RESPONSIBILITY UNDER THE ROAD TRAFFIC REGULATION ACT 1984, THE ROAD TRAFFIC OFFENDER ACT 1988,
        AND/OR ANY SUBSEQUENT RELEVANT LEGISLATION.
    </p>

    <p style="text-align: justify; margin-bottom: 25px;">
        ANY BREAKDOWN SERVICE IS REQUIRED ON VEHICLE IS DRIVER RESPONSIBILITY.
    </p>

    <p style="font-weight: bold; margin-bottom: 15px;">DETAILS ARE AS FOLLOW:</p>

    {{-- SOL - Details Fields --}}
    <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
        <tr>
            <td style="padding: 8px 0; width: 35%;"><strong>HIRER NAME: MR</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #000;">{{ $driver->full_name }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>HIRER ADDRESS:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #000;">
                {{ $driver->address1 ?? '' }}{{ isset($driver->address2) && $driver->address2 ? ', ' . $driver->address2 : '' }}
                {{ isset($driver->post_code) ? ' ' . $driver->post_code : '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>MAKE AND MODEL:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #000;">{{ $car->carModel->name ?? '' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>VEHICLE REGISTRATION:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #000;">{{ $car->registration }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>DATE/TIME OUT:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #000;">{{ $agreement->start_date->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0;"><strong>DATE/TIME DUE:</strong></td>
            <td style="padding: 8px 0; border-bottom: 1px solid #000;">{{ $agreement->end_date->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    {{-- SOL - Consent Statement --}}
    <p style="margin-top: 25px; font-weight: bold; text-align: center;">
        I HAVE READ AND HAPPY TO SIGN FOR THIS STATEMENT OF LIABILITY.
    </p>

    {{-- SOL - Client Signature --}}
    <div style="text-align: center; margin-top: 30px;">
        <div style="display: inline-block; width: 40%; text-align: center;">
            <div style="border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px;"></div>
            <strong>CLIENT SIGNATURE</strong>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #666;">
        Agreement ID: {{ $agreement->id }} | Statement of Liability
    </div>
</div>
</body>
</html>
