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
                border: 1px solid black;
                padding: .25rem .5rem;
            }

            .table th {
                background-color: #dddddd;
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
                width: 3.5rem;
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