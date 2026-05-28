<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>របាយការណ៍វត្តមានបុគ្គលិក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            max-width: 50px;
            vertical-align: middle;
        }
        .header h1 {
            color: #FFD700; /* Gold color for "Van Van Cambodia" */
            margin: 0;
            display: inline;
            font-size: 2em;
        }
        .header p {
            color: #333;
            margin: 5px 0;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 2px solid #1E90FF; /* Blue border like the form */
        }
        th, td {
            border: 1px solid #1E90FF;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #1E90FF; /* Blue background for headers */
            color: white;
        }
        .section-header {
            background-color: #FFD700; /* Yellow background for section headers */
            color: #1E90FF;
            font-weight: bold;
        }
        .overall-total {
            background-color: #ECECEC;
            font-weight: bold;
            font-size: 1.1em;
        }
        .general-comment {
            margin-top: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .general-comment input[type="text"] {
            width: 100%;
            max-width: 100%;
            min-height: 50px;
            padding: 5px;
            border: 1px solid #1E90FF;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', sans-serif;
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
        }
        .footer p {
            margin: 5px 0;
        }
        .signature {
            margin-top: 20px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <!-- Placeholder for logo -->
            <h1>វ៉ាន់ វ៉ាន់ ខេមបូឌា</h1>
            <p>អាស័យដ្ឋាន: ផ្ទះលេខ ១៩៩ ផ្លូវលេខ ១៥៥ សង្កាត់ទួលទំពូងទី១ ខណ្ឌចំការមន រាជធានីភ្នំពេញ</p>
            <p>លេខទូរស័ព្ទ: ០២៣ ២១៩ ៩៩៩ / ០៩៨ ២១៩ ៩៩៩</p>
        </div>

        <!-- Title -->
        <h2>របាយការណ៍វត្តមានបុគ្គលិកនៅការិយាល័យកណ្តាល និងតាមឃ្លាំង</h2>

        <!-- General Comment Input (Static) -->
        <div class="general-comment">
            <input type="text" id="general_comment" name="general_comment" value="ថ្ងៃទី ១០ ខែ មីនា ឆ្នាំ ២០២៥" readonly>
        </div>

        <!-- Office Staff Section -->
        <table>
            <tr><th colspan="4" class="section-header">បុគ្គលិកការិយាល័យកណ្តាល</th></tr>
            <tr>
                <th>ទីតាំង</th>
                <th>ចំនួនសរុប</th>
                <th>បុគ្គលិកភេទស្រី</th>
                <th>បុគ្គលិកភេទប្រុស</th>
            </tr>
            <tr>
                <td>ការិយាល័យកណ្តាល</td>
                <td>20</td>
                <td>10</td>
                <td>10</td>
            </tr>
        </table>

        <!-- Store 318 Staff Section -->
        <table>
            <tr><th colspan="3" class="section-header">បុគ្គលិកហាងទំនិញ៣១៨</th></tr>
            <tr>
                <th>ចំនួនសរុប</th>
                <th>បុគ្គលិកភេទស្រី</th>
                <th>បុគ្គលិកភេទប្រុស</th>
            </tr>
            <tr>
                <td>15</td>
                <td>8</td>
                <td>7</td>
            </tr>
        </table>

        <!-- Warehouse Staff Section -->
        <table>
            <tr><th colspan="5" class="section-header">បុគ្គលិកជំនាញតាមឃ្លាំង</th></tr>
            <tr>
                <th>ចំនួនសរុប</th>
                <th>CH1</th>
                <th>CKD</th>
                <th>ST1</th>
                <th>PSP</th>
            </tr>
            <tr>
                <td>30</td>
                <td>10</td>
                <td>8</td>
                <td>6</td>
                <td>6</td>
            </tr>
        </table>

        <!-- New Staff Section -->
        <table>
            <tr><th colspan="7" class="section-header">បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី</th></tr>
            <tr>
                <th>ល.រ</th>
                <th>ឈ្មោះ</th>
                <th>តួនាទី</th>
                <th>អធិប្បាយ</th>
                <th>ការិយាល័យកណ្តាល</th>
                <th>៣១៨</th>
                <th>ឃ្លាំង</th>
            </tr>
            <tr>
                <td>1</td>
                <td>សុខ សុភ័ក្ត្រ</td>
                <td>អ្នកគ្រប់គ្រង</td>
                <td>សុំច្បាប់</td>
                <td>1</td>
                <td>0</td>
                <td>0</td>
            </tr>
            <tr>
                <td>2</td>
                <td>លី សុជាតិ</td>
                <td>បុគ្គលិកថ្មី</td>
                <td>ចូលថ្មី</td>
                <td>0</td>
                <td>1</td>
                <td>0</td>
            </tr>
        </table>

        <!-- Overall Total Section -->
        <table>
            <tr><th colspan="5" class="section-header">សរុបរួម</th></tr>
            <tr>
                <th>ការិយាល័យកណ្តាល</th>
                <th>ហាងទំនិញ៣១៨</th>
                <th>ឃ្លាំង</th>
                <th>បុគ្គលិកថ្មី</th>
                <th>សរុបទាំងអស់</th>
            </tr>
            <tr class="overall-total">
                <td>20</td>
                <td>15</td>
                <td>30</td>
                <td>2</td>
                <td>67</td>
            </tr>
        </table>

        <!-- Footer Section -->
        <div class="footer">
            <p>រាជធានីភ្នំពេញ ថ្ងៃទី ១០ ខែ មីនា ឆ្នាំ ២០២៥</p>
            <p>អ្នករៀបចំរបាយការណ៍</p>
        </div>
        <div class="signature">
            <p>ហត្ថលេខា</p>
        </div>
    </div>
</body>
</html>