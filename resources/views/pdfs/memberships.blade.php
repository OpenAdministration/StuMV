<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <style>
            body {
                font-family: sans-serif;
                font-size: 11pt;
                padding-left: 3rem;
                padding-right: 3rem;
                padding-top: 1rem;
                padding-bottom: 1rem;
                word-wrap: break-word;
                hyphens: auto;
            }

            h1 {
                margin-top: 0;
                font-size: 15pt;
            }

            h2 {
                font-size: 13pt;
                margin-top: 1.25rem;
            }
            
            hr {
                border: none;
                border-bottom: 1px solid black;
            }

            th {
                text-align: left;
                vertical-align: top;
                width: 8rem;
                padding-left: 0;
            }

            .no-padding {
                padding: 0;
            }

            .signing-field {
                height: 5rem;
                width: 11.8rem;
                border-bottom: 1px solid black;
                margin-right: 1rem;
            }

            .table {
                border-collapse: collapse;
                width: 100%;
            }

            .table th,
            .table td {
                border-top: 1px solid #ddd;
                padding: .5rem .75rem;
            }

            .table tr th:first-of-type,
            .table tr td:first-of-type {
                padding-left: 0;
            }

            .table tr th:last-of-type,
            .table tr td:last-of-type {
                padding-right: 0;
            }

            .table tr:last-of-type {
                border-bottom: 1px solid #ddd;
            }

            .float-right {
                float: right;
            }

            .text-small {
                font-size: 9pt;
            }

            .mt-4 {
                margin-top: 1rem;
            }

            .mb-6 {
                margin-bottom: 1.5rem;
            }

            .-mb-2 {
                margin-bottom: -.5rem;
            }

            .w-date {
                width: 1.5cm;
                white-space: nowrap;
            }
        </style>
    </head>

    <body>
        <h1>{{ __('profile.activities') }}</h1>
        <p class="mb-6">{{ $fullName }} @if($community)| {{ $community }} @endif| {{ date("Y-m-d") }}</p>

        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('profile.role') }}</th>
                    <th>{{ __('profile.committee') }}</th>
                    <th class="w-date">{{ __('profile.from') }}</th>
                    <th class="w-date">{{ __('profile.until') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($memberships as $row)
                <tr>
                    <td>{{ $row['role']->getFirstAttribute('description') }}</td>
                    <td>{{ $row['role']->committee()->getFirstAttribute('description') }}</td>
                    <td>{{ \Carbon\Carbon::parse($row['from'])->format('Y-m-d') }}</td>
                    <td>
                        @if ($row['until'] != '')
                        {{ \Carbon\Carbon::parse($row['until'])->format('Y-m-d') }}
                        @else
                        {{ __('profile.today') }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </body>
</html>