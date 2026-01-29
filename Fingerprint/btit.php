<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ប្រព័ន្ធស្កេនចូល/ចេញសម្រាប់តាមដានវត្តមាន" />
    <meta name="app-version" content="1.0.3" />
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#3b5998">
    <style>
        /* Fallback for browsers that do not support theme-color */
        body {
            background-color: #3b5998;
        }
    </style>
    <link rel="apple-touch-icon" href="https://i.ibb.co/W4cSS74n/Logo-Van-Van-1.png">
    <meta name="msapplication-TileColor" content="#3b5998">
    <title>ប្រព័ន្ធស្កេនចូល/ចេញ</title>
    <link rel="manifest" href="manifest-3.json?v=1.0.3">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css?v=1.0.1"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Khmer&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css?v=1.0.1" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Noto+Sans+Khmer:wght@100..900&display=swap');
    </style>
    <link rel="icon" href="https://i.ibb.co/W4cSS74n/Logo-Van-Van-1.png" type="image/png">
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <style>
        /* General Mobile-First Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Kantumruy Pro', 'Noto Sans Khmer', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
            touch-action: manipulation;
            height: 100%;
        }

        body {
            background: linear-gradient(180deg, #e0f2f7, #c6e9f1);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 1rem;
            line-height: 1.5;
            overflow: hidden;
            overscroll-behavior: none;
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite alternate;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        .container {
            width: 100%;
            max-width: 100%;
            flex-grow: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 70px;
        }

        .card {
            width: 100%;
            background-repeat: no-repeat;
            background-position: center;
            position: relative;
            border-radius: 18px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            border: 2px solid #4A90E2;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url(https://i.ibb.co/wrQsZBQs/Logo-Van-Van-3.png);
            background-size: 600px 600px;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 20%;
            z-index: 0;
            pointer-events: none;
        }

        .card>* {
            position: relative;
            z-index: 1;
        }

        @media (pointer: coarse) {
            html {
                touch-action: manipulation;
            }
        }

        .card-body {
            padding: 1.8rem 1.5rem;
            flex-grow: 1;
        }

        h1 {
            font-size: 1.75rem;
            color: #2C3E50;
            text-align: center;
            margin-bottom: 1.8rem;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: -0.02em;
        }

        .form-label {
            font-size: 0.95rem;
            color: #34495E;
            font-weight: 600;
            margin-bottom: 0.6rem;
            display: block;
        }

        .form-select,
        .btn,
        .form-control {
            width: 100%;
            padding: 0.9rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.2rem;
            border: 1px solid #E0E0E0;
            background: #F8F9FA;
            font-size: 1.05rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #4A90E2;
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.2);
            background: #FFFFFF;
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4A90E2, #3F7DCF);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
            font-size: 1.1rem;
            touch-action: manipulation;
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
            transition: all 0.2s ease;
        }

        .btn-primary:hover,
        .btn-primary:active {
            background: linear-gradient(135deg, #3F7DCF, #326BB8);
            box-shadow: 0 6px 16px rgba(74, 144, 226, 0.4);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #FFC107, #FFA000);
            color: white;
            border: none;
            padding: 0.9rem;
            font-size: 0.95rem;
            box-shadow: 0 3px 10px rgba(255, 165, 0, 0.2);
            transition: all 0.2s ease;
        }

        .btn-warning:hover,
        .btn-warning:active {
            background: linear-gradient(135deg, #FFA000, #E68A00);
            box-shadow: 0 4px 12px rgba(255, 165, 0, 0.3);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #B0BEC5, #90A4AE);
            color: white;
            border: none;
            padding: 0.9rem;
            font-size: 0.95rem;
            box-shadow: 0 3px 10px rgba(176, 190, 197, 0.2);
            transition: all 0.2s ease;
        }

        .btn-secondary:hover,
        .btn-secondary:active {
            background: linear-gradient(135deg, #90A4AE, #78909C);
            box-shadow: 0 4px 12px rgba(176, 190, 197, 0.3);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #E74C3C, #C0392B);
            color: white;
            border: none;
            padding: 0.9rem;
            font-size: 0.95rem;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.2);
            transition: all 0.2s ease;
        }

        .btn-danger:hover,
        .btn-danger:active {
            background: linear-gradient(135deg, #C0392B, #A93226);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
            transform: translateY(-1px);
        }

        .status-message {
            font-size: 0.9rem;
            padding: 0.8rem;
            border-radius: 0.5rem;
            text-align: center;
            margin-top: 1rem;
            background: #EBF5FB;
            border: 1px solid #BEE3F8;
            color: #2C5282;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            opacity: 0;
            transform: translateY(0.5rem);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .status-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        #loginSection,
        #scanSection {
            display: none;
        }

        #loginSection.active,
        #scanSection.active {
            display: flex;
            flex-direction: column;
            height: auto;
        }

        .spinner {
            display: none;
            margin-right: 0.5rem;
            width: 1.25rem;
            height: 1.25rem;
        }

        .action-info p {
            font-size: 0.95rem;
            color: #34495E;
            margin-bottom: 0.7rem;
            padding: 0.8rem;
            background: #F0F4F8;
            border-radius: 0.6rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .action-info p strong {
            color: #4A90E2;
            font-weight: 600;
        }

        .action-info span {
            font-weight: 500;
            color: #2C3E50;
            text-align: right;
            flex-grow: 1;
        }

        .action-info #mapLink {
            color: #4A90E2;
            text-decoration: none;
            font-weight: 600;
        }

        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #FFFFFF;
            padding: 1.8rem;
            border-radius: 1.2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            text-align: center;
            width: 90%;
            max-width: 22rem;
            animation: popupFadeIn 0.3s ease-out;
            border: none;
        }

        .popup.success {
            border: 2px solid #2ECC71;
        }

        .popup.error {
            border: 2px solid #E74C3C;
        }

        .popup p {
            margin: 0;
            font-size: 1.05rem;
            color: #2C3E50;
            font-weight: 500;
        }

        @keyframes popupFadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            justify-content: center;
            align-items: center;
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background: #FFFFFF;
            padding: 1.8rem;
            border-radius: 1.2rem;
            width: 90%;
            max-width: 24rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            border: none;
            animation: popupFadeIn 0.3s ease-out;
        }

        .modal-content h3 {
            color: #E74C3C;
            margin-bottom: 1.2rem;
            font-size: 1.4rem;
            font-weight: 600;
            text-align: center;
        }

        .modal-buttons {
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-around;
            gap: 0.8rem;
        }

        .modal-buttons button {
            flex: 1;
            padding: 0.9rem;
            border-radius: 0.75rem;
            font-size: 1rem;
        }

        .fullscreen-loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            backdrop-filter: blur(5px);
        }

        .fullscreen-loading.show {
            display: flex;
        }

        .fullscreen-loading .spinner-border {
            width: 2.5rem;
            height: 2.5rem;
            border-width: 0.3rem;
            margin-bottom: 1rem;
            color: #FFFFFF;
}
        .fullscreen-loading p {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 500;
            letter-spacing: 0.05em;
        }

        #map {
            height: 12rem;
            width: 100%;
            border: 1px solid #E0E0E0;
            border-radius: 0.75rem;
            background: #F0F4F8;
            margin-top: 0.8rem;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .leaflet-container {
            font-family: 'Kantumruy Pro', sans-serif;
        }

        /* Footer Styles (Mobile App Navigation Bar) */
        .custom-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            border-top: 1px solid #E0E0E0;
            z-index: 1100;
            box-shadow: 0 -4px 15px rgba(44, 62, 80, 0.08);
            padding: 0.7rem 0;
            transition: background 0.3s;
        }

        .footer-content {
            display: flex;
            justify-content: space-around;
            align-items: center;
            max-width: 480px;
            margin: 0 auto;
        }

        .footer-btn {
            background: none;
            border: none;
            outline: none;
            color: #7F8C8D;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.5rem 0.8rem;
            transition: color 0.2s, background 0.2s, transform 0.2s;
            border-radius: 0.75rem;
            min-width: 80px;
            font-family: inherit;
            text-decoration: none;
        }

        .footer-btn i {
            margin-bottom: 0.15rem;
            font-size: 1.5rem;
            color: inherit;
        }

        .footer-btn span {
            font-size: 0.85rem;
            font-weight: 500;
            color: inherit;
            margin-top: 0.1rem;
            letter-spacing: 0.01em;
        }

        .footer-btn.active,
        .footer-btn:focus {
            background: #EAF0FA;
            color: #4A90E2;
            transform: translateY(-2px);
        }

        .footer-btn:active {
            transform: translateY(0);
        }

        .footer-btn:nth-child(1).active,
        .footer-btn:nth-child(1):focus {
            color: #4A90E2;
        }

        .footer-btn:nth-child(2).active,
        .footer-btn:nth-child(2):focus {
            color: #F39C12;
        }

        /* Profile Popup Styles */
        .profile-popup {
            display: none;
            position: fixed;
            z-index: 2001;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(44, 62, 80, 0.25);
            justify-content: center;
            align-items: center;
            animation: fadeInProfileBg 0.2s;
            backdrop-filter: blur(3px);
        }

        .profile-popup.show {
            display: flex;
        }

        .profile-popup-content {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 10px 40px rgba(44, 62, 80, 0.2);
            padding: 2.5rem 1.8rem 1.8rem 1.8rem;
            min-width: 280px;
            max-width: 95vw;
            width: 360px;
            position: relative;
            animation: popupFadeIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-popup-close {
            position: absolute;
            top: 0.9rem;
            right: 1.2rem;
            background: none;
            border: none;
            font-size: 2rem;
            color: #B2BEC3;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 2;
        }

        .profile-popup-close:hover {
            color: #E74C3C;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #EAF0FA 60%, #D6EAFF 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(44, 62, 80, 0.1);
        }

        .profile-avatar i {
            font-size: 3rem;
            color: #4A90E2;
        }

        .profile-info {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            font-size: 1.1rem;
            color: #34495E;
        }

        .profile-info div {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.3rem 0;
            border-bottom: 1px dotted #e9ecef;
        }

        .profile-info div:last-child {
            border-bottom: none;
        }

        .profile-info span {
            font-weight: 600;
            color: #4A90E2;
            min-width: 100px;
            display: inline-block;
            text-align: left;
        }

        .profile-info b {
            font-weight: 500;
            color: #222;
            word-break: break-all;
            text-align: right;
            flex-grow: 1;
        }

        /* Scan Logs Popup Styles */
        .scan-logs-popup {
            display: none;
            position: fixed;
            z-index: 2002;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(44, 62, 80, 0.18);
            justify-content: center;
            align-items: center;
            animation: fadeInProfileBg 0.2s;
            backdrop-filter: blur(3px);
        }

        .scan-logs-popup.show {
            display: flex;
        }

        .scan-logs-popup-content {
            background: #FFFFFF;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            min-width: 95vw;
            max-width: 95vw;
            width: 95vw;
            position: relative;
            animation: popupFadeIn 0.3s ease-out;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            max-height: 90vh;
            height: auto;
        }

        .scan-logs-popup-close {
            position: absolute;
            top: 0.8rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.8rem;
            color: #888;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 2;
        }

        .scan-logs-popup-close:hover {
            color: #E74C3C;
        }

        .scan-logs-list {
            overflow-y: auto;
            max-height: calc(90vh - 3rem - 1.5rem);
            padding-right: 0.5rem;
            padding-left: 0.5rem;
            padding-bottom: 0.5rem;
            padding-top: 0.5rem;
        }

        .scan-log-item {
            background: #F9F9F9;
            border-radius: 0.75rem;
            margin-bottom: 0.8rem;
            padding: 1rem;
            font-size: 0.95rem;
            color: #34495E;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .scan-log-item:last-child {
            margin-bottom: 0;
        }

        .scan-log-item div {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .scan-log-label {
            font-weight: 600;
            color: #4A90E2;
            min-width: 80px;
        }

        .scan-log-value {
            font-weight: 500;
            color: #2C3E50;
            text-align: right;
            flex-grow: 1;
            word-break: break-word;
        }

        .scan-log-status.success {
            color: #2ECC71;
            font-weight: 700;
        }

        .scan-log-status.error {
            color: #E74C3C;
            font-weight: 700;
        }

        .scan-log-date,
        .scan-log-location,
        .scan-log-address {
            color: #6C7A89;
            font-size: 0.9em;
        }

        .scan-log-location a {
            color: #4A90E2;
            text-decoration: none;
            font-weight: 600;
        }

        /* PWA Install Popup Styles */
        #pwaInstallPopup.popup {
            background: #FFFFFF;
            border: 2px solid #4A90E2;
            color: #2C3E50;
            font-size: 1rem;
            padding: 1.8rem;
            border-radius: 1.2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            max-width: 25rem;
            text-align: left;
        }

        #pwaInstallPopup strong {
            color: #4A90E2;
            display: block;
            margin-bottom: 0.8rem;
            font-size: 1.15rem;
        }

        #pwaInstallPopup span {
            display: block;
            margin-bottom: 0.6rem;
            line-height: 1.4;
        }

        #pwaInstallPopup i {
            color: #4A90E2;
            margin: 0 4px;
        }

        #pwaInstallPopup .btn {
            margin-top: 1rem;
            width: auto;
            display: inline-block;
            padding: 0.75rem 1.2rem;
            font-size: 1rem;
        }

        #pwaInstallPopup .btn-secondary {
            margin-left: 0.8rem;
        }

        @media (max-width: 768px) {
            html {
                font-size: 15px;
            }
            .card-body {
                padding: 1.5rem 1.2rem;
            }
            h1 {
                font-size: 1.5rem;
            }
            .form-select,
            .btn,
            .form-control {
                padding: 0.8rem 0.9rem;
                font-size: 1rem;
            }
            .btn-primary {
                padding: 0.9rem;
                font-size: 1.05rem;
            }
            .popup,
            .modal-content {
                width: 95%;
                max-width: 20rem;
                padding: 1.5rem;
            }
            .modal-content h3 {
                font-size: 1.3rem;
            }
            #map {
                height: 10rem;
            }
            .profile-popup-content {
                width: 90%;
                max-width: 320px;
                padding: 2rem 1.2rem 1.2rem 1.2rem;
            }
            .profile-avatar {
                width: 70px;
                height: 70px;
            }
            .profile-avatar i {
                font-size: 2.5rem;
            }
            .profile-info {
                font-size: 1rem;
                gap: 0.7rem;
            }
            .profile-info span {
                min-width: 80px;
            }
            .scan-logs-popup-content {
                min-width: 98vw;
                max-width: 98vw;
                width: 98vw;
                padding: 1rem;
            }
            .scan-logs-list {
                max-height: calc(90vh - 2rem - 1.5rem);
            }
            .scan-log-item {
                padding: 0.8rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 400px) {
            html {
                font-size: 14px;
            }
            .container {
                padding: 0.8rem;
            }
            .card-body {
                padding: 1.2rem;
            }
            h1 {
                font-size: 1.4rem;
                margin-bottom: 1.5rem;
            }
            .form-label {
                font-size: 0.9rem;
            }
            .form-select,
            .btn,
            .form-control {
                padding: 0.7rem 0.8rem;
                font-size: 0.95rem;
                margin-bottom: 1rem;
            }
            .btn-primary {
                padding: 0.8rem;
                font-size: 1rem;
            }
            .popup,
            .modal-content {
                padding: 1.2rem;
                max-width: 18rem;
            }
            .popup p {
                font-size: 0.95rem;
            }
            .modal-content h3 {
                font-size: 1.15rem;
            }
            .modal-buttons button {
                padding: 0.7rem;
                font-size: 0.9rem;
            }
            .fullscreen-loading .spinner-border {
                width: 2rem;
                height: 2rem;
            }
            .fullscreen-loading p {
                font-size: 1rem;
            }
            #map {
                height: 8rem;
            }
            .footer-btn {
                min-width: 65px;
                padding: 0.4rem 0.6rem;
            }
            .footer-btn i {
                font-size: 1.3rem;
            }
            .footer-btn span {
                font-size: 0.75rem;
            }
            .profile-popup-content {
                width: 95%;
                max-width: 280px;
                padding: 1.5rem 1rem 1rem 1rem;
            }
            .profile-avatar {
                width: 60px;
                height: 60px;
            }
            .profile-avatar i {
                font-size: 2rem;
            }
            .profile-info {
                font-size: 0.9rem;
                gap: 0.5rem;
            }
            .profile-info span {
                min-width: 70px;
            }
            .scan-log-item {
                padding: 0.7rem;
                font-size: 0.85rem;
            }
            #pwaInstallPopup.popup {
                padding: 1.2rem;
                max-width: 20rem;
                font-size: 0.95rem;
            }
            #pwaInstallPopup strong {
                font-size: 1.05rem;
            }
            #pwaInstallPopup .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }

        @media (-webkit-min-device-pixel-ratio: 2),
        (min-resolution: 192dpi) {
            .card,
            .btn,
            .form-control,
            .form-select {
                border-width: 0.5px;
            }
        }

        @media (orientation: landscape) and (max-height: 500px) {
            .container {
                padding: 0.5rem;
                padding-bottom: 60px;
            }
            .card-body {
                padding: 1rem;
            }
            #map {
                height: 6rem;
            }
            .footer-btn {
                font-size: 0.9rem;
                min-width: 50px;
            }
            .footer-btn i {
                font-size: 1rem;
            }
            .footer-btn span {
                font-size: 0.7rem;
            }
            .profile-popup-content,
            .scan-logs-popup-content,
            #pwaInstallPopup.popup {
                max-height: 95vh;
                overflow-y: auto;
            }
        }

        @supports (padding: env(safe-area-inset-top)) {
            body {
                padding-top: env(safe-area-inset-top);
                padding-bottom: env(safe-area-inset-bottom);
                padding-left: env(safe-area-inset-left);
                padding-right: env(safe-area-inset-right);
            }
            .custom-footer {
                padding-bottom: calc(0.7rem + env(safe-area-inset-bottom));
            }
            .container {
                padding-bottom: calc(70px + env(safe-area-inset-bottom));
            }
        }

        @keyframes fadeInProfileBg {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes bouncyPopupFadeIn {
            0% {
                opacity: 0;
                transform: translate(-50%, -70%) scale(0.8);
            }
            60% {
                opacity: 1;
                transform: translate(-50%, -45%) scale(1.05);
            }
            100% {
                transform: translate(-50%, -50%) scale(1);
            }
        }

                    @keyframes stickerUpDown {
                0% {
                    transform: translateY(0);
                }

                50% {
                    transform: translateY(-30px) scale(1.07) rotate(-4deg);
                }

                100% {
                    transform: translateY(0);
                }
            }

            @keyframes stickerLeftRight {
                0% {
                    transform: translateX(0);
                }

                50% {
                    transform: translateX(-30px) scale(1.07) rotate(4deg);
                }

                100% {
                    transform: translateX(0);
                }
            }

             .custom-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-image: url(https://www.shutterstock.com/image-vector/abstract-blue-background-modern-simple-600nw-2474145007.jpg);
            border-top: 1px solid #e0e0e0;
            z-index: 1100;
            border-radius: 26px 26px 0 0;
            box-shadow: 0 -2px 12px rgba(44, 62, 80, 0.06);
            padding: 1rem;
            transition: background 0.3s;
        }

        .footer-content {
            display: flex;
            justify-content: space-around;
            align-items: center;
            max-width: 480px;
            margin: 0 auto;
        }

        .footer-btn {
            background: none;
            border: none;
            outline: none;
            color: #ffffff;
            font-size: 5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.25rem 0.5rem;
            transition: color 0.2s, background 0.2s;
            border-radius: 0.75rem;
            min-width: 80px;
            font-family: inherit;
        }

        .footer-btn i {
            margin-bottom: 0.15rem;
            font-size: 5rem;
        }

        .footer-btn span {
            font-size: 3rem;
            font-weight: 500;
            color: #ffffff;
            margin-top: 0.05rem;
            letter-spacing: 0.01em;
        }

        .footer-btn:active,
        .footer-btn:focus {
            background: #eaf0fa;
            color: #ffffff;
        }

        .footer-btn:nth-child(2) {
            color: #ffffff;
        }

        .footer-btn:nth-child(2):active,
        .footer-btn:nth-child(2):focus {
            background: #fbeee0;
            color: #ffffff;
        }

        @media (max-width: 600px) {
            .footer-content {
                max-width: 100%;
            }

            .footer-btn {
                font-size: 1.1rem;
                min-width: 70px;
            }

            .footer-btn i {
                font-size: 1.2rem;
            }

            .footer-btn span {
                font-size: 0.85rem;
            }
        }

        /* Profile Popup Styles */
        .profile-popup {
            display: none;
            position: fixed;
            z-index: 2001;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(44, 62, 80, 0.25);
            justify-content: center;
            align-items: center;
            animation: fadeInProfileBg 0.2s;
        }

        .profile-popup.show {
            display: flex;
        }

        .profile-popup-content {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 8px 32px rgba(44, 62, 80, 0.18);
            padding: 2.2rem 1.5rem 1.5rem 1.5rem;
            min-width: 270px;
            max-width: 95vw;
            width: 340px;
            position: relative;
            animation: popupFadeIn 0.25s;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-popup-close {
            position: absolute;
            top: 0.7rem;
            right: 1.1rem;
            background: none;
            border: none;
            font-size: 1.7rem;
            color: #b2bec3;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 2;
        }

        .profile-popup-close:hover {
            color: #e74c3c;
        }

        .profile-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #eaf0fa 60%, #d6eaff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.1rem;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.07);
        }

        .profile-avatar i {
            font-size: 2.5rem;
            color: #2980b9;
        }

        .profile-info {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            font-size: 1.08rem;
            color: #34495e;
        }

        .profile-info span {
            font-weight: 600;
            color: #2980b9;
            min-width: 90px;
            display: inline-block;
        }

        .profile-info b {
            font-weight: 500;
            color: #222;
            word-break: break-all;
        }

        @media (max-width: 400px) {
            .profile-popup-content {
                min-width: 180px;
                padding: 1.2rem 0.5rem 1rem 0.5rem;
            }

            .profile-avatar {
                width: 48px;
                height: 48px;
            }

            .profile-avatar i {
                font-size: 1.5rem;
            }

            .profile-info {
                font-size: 0.98rem;
            }
        }

        /* Scan Logs Popup Styles */
        .scan-logs-popup {
            display: none;
            position: fixed;
            z-index: 2002;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(44, 62, 80, 0.18);
            justify-content: center;
            align-items: center;
            animation: fadeInProfileBg 0.2s;
        }

        .scan-logs-popup.show {
            display: flex;
        }

        .scan-logs-popup-content {
            border-radius: 8px;
            box-shadow: none;
            padding: 0;
            min-width: 100vw;
            max-width: 100vw;
            width: 100vw;
            position: relative;
            animation: popupFadeIn 0.25s;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            max-height: 70vh;
            height: 100vh;
        }

        .scan-logs-popup-close {
            position: absolute;
            top: 0.7rem;
            right: 1.1rem;
            background: none;
            border: none;
            font-size: 18px;
            color: #000000;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 2;
        }

        .scan-logs-popup-close:hover {
            color: #e74c3c;
        }

        .scan-logs-list {
            overflow-y: auto;
            max-height: calc(100vh - 3.5rem);
            padding-right: 0.5rem;
            padding-left: 0.5rem;
            padding-bottom: 1.5rem;
            padding-top: 0.5rem;
        }

        .scan-log-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 0.7rem 0.2rem;
            font-size: 1.01rem;
            color: #34495e;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .scan-log-item:last-child {
            border-bottom: none;
        }

        .scan-log-type {
            font-weight: 600;
            color: #2980b9;
            margin-right: 0.5rem;
        }

        .scan-log-status {
            font-size: 0.97em;
            font-weight: 500;
            margin-left: 0.3rem;
        }

        .scan-log-date {
            font-size: 0.95em;
            color: #888;
        }

        .scan-log-location {
            font-size: 0.93em;
            color: #888;
            margin-top: 0.1rem;
        }

        .scan-log-address {
            font-size: 0.93em;
            color: #888;
            margin-top: 0.1rem;
        }

        @media (max-width: 400px) {
            .scan-logs-popup-content {
                min-width: 100vw;
                padding: 0;
                width: 100vw;
            }

            .scan-logs-list {
                max-height: calc(200vh - 3.5rem);
                padding-left: 0.2rem;
                padding-right: 0.2rem;
            }
        }

        @keyframes fadeInProfileBg {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes popupFadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

    </style>
</head>

<body>
    <div class="container">
        <div class="card" id="loginSection">
            <div class="card-body">
                <h1>ចូលប្រើប្រព័ន្ធ</h1>
                <form id="loginForm" class="mb-3">
                    <div class="mb-3">
                        <label for="loginId" class="form-label">ID</label>
                        <input type="text" class="form-control" id="loginId" placeholder="បញ្ចូល ID របស់អ្នក" required
                            autocomplete="off">
                    </div>
                    <button type="submit" id="loginButton" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true"></span>
                        ចូល
                    </button>
                </form>
                <p id="loginStatus" class="status-message"></p>
            </div>
        </div>

        <header class="custom-header">
      <div class="header-content">
        <img src="https://i.ibb.co/qLg5cVXS/Logo-Van-Van-3.png" alt="Logo">
        <div style="flex:1;">
          <span class="header-title">ប្រព័ន្ធស្កេនចូល/ចេញ</span>
          <div class="header-desc">សម្រាប់តាមដានវត្តមាន</div>
        </div>
        <!-- Clock Display -->
        <div id="headerClock" style="color:#fff;font-size:1.08rem;font-weight:600;min-width:90px;text-align:right;letter-spacing:0.01em;display:flex;align-items:center;gap:0.4em;flex-direction:column;align-items:flex-end;">
          <div style="display:flex;align-items:center;gap:0.4em;">
            <i class="fa-regular fa-clock" style="font-size:1.15em;opacity:0.85;"></i>
            <span id="clockTime" style="font-family:'Kantumruy Pro', 'Noto Sans Khmer', 'Khmer', Arial,sans-serif;letter-spacing:0.02em;background:rgba(255,255,255,0.13);padding:0.18em 0.7em;border-radius:0.7em;box-shadow:0 1px 4px #0001;font-size:1.08em;"></span>
          </div>
          <span id="clockDate" style="font-size:0.92em;color:#eaf6fb;opacity:0.85;margin-top:0.1em;font-weight:500;letter-spacing:0.01em;"></span>
        </div>
        <style>
          #headerClock {
            user-select: none;
            transition: background 0.18s;
          }
          #headerClock #clockTime {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 1px 4px #0002;
            letter-spacing: 0.03em;
            transition: background 0.18s, color 0.18s;
          }
          #headerClock #clockDate {
            font-size: 0.92em;
            color: #eaf6fb;
            opacity: 0.85;
            margin-top: 0.1em;
            font-weight: 500;
            letter-spacing: 0.01em;
          }
          @media (max-width: 600px) {
            #headerClock {
              font-size: 0.98rem;
              min-width: 70px;
            }
            #headerClock #clockTime {
              font-size: 0.98em;
              padding: 0.13em 0.5em;
            }
            #headerClock #clockDate {
              font-size: 0.85em;
            }
          }
        </style>
        <script>
          function updateHeaderClock() {
            const el = document.getElementById('clockTime');
            const dateEl = document.getElementById('clockDate');
            if (el) {
              const now = new Date();
              let h = now.getHours();
              let m = now.getMinutes();
              let s = now.getSeconds();
              const ampm = h >= 12 ? 'PM' : 'AM';
              h = h % 12;
              if (h === 0) h = 12;
              el.textContent = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')} ${ampm}`;
            }
            if (dateEl) {
              const now = new Date();
              // Khmer date format: ថ្ងៃទី dd ខែ mm ឆ្នាំ yyyy
              const days = ['អាទិត្យ','ច័ន្ទ','អង្គារ','ពុធ','ព្រហស្បតិ៍','សុក្រ','សៅរ៍'];
              const months = ['មករា','កម្ភៈ','មិនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
              const day = days[now.getDay()];
              const d = now.getDate().toString().padStart(2, '0');
              const m = months[now.getMonth()];
              const y = now.getFullYear();
              dateEl.textContent = `ថ្ងៃ${day} ទី${d} ខែ${m} ឆ្នាំ${y}`;
            }
          }
          document.addEventListener('DOMContentLoaded', function () {
            updateHeaderClock();
            setInterval(updateHeaderClock, 1000);

            // Hide header on login page
            const loginSection = document.getElementById('loginSection');
            const header = document.querySelector('header.custom-header');
            function updateHeaderVisibility() {
              if (loginSection && header) {
          if (loginSection.style.display === 'none') {
            header.style.display = '';
          } else {
            header.style.display = 'none';
          }
              }
            }
            updateHeaderVisibility();
            const observer = new MutationObserver(updateHeaderVisibility);
            observer.observe(loginSection, { attributes: true, attributeFilter: ['style', 'class'] });
            window.addEventListener('resize', updateHeaderVisibility);
          });
        </script>

      </div>
    </header>
    <style>
      .custom-header {
        width: 100%;
        max-width: 100vw;
        position: relative;
        z-index: 100;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.2rem;
      }

      .header-content {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 1.1rem 1.2rem;
        width: 100%;
        max-width: 480px;
        background: linear-gradient(90deg, #4A90E2 60%, #226694 100%);
        border-radius: 1.2rem;
        box-shadow: 0 2px 8px rgba(44, 62, 80, 0.07);
      }

      .header-content img {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 4px #0001;
      }

      .header-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
        letter-spacing: -0.01em;
        display: block;
      }

      .header-desc {
        font-size: 0.95rem;
        color: #eaf6fb;
        font-weight: 500;
      }

      .header-version {
        font-size: 0.85rem;
        color: #eaf6fb;
        font-weight: 500;
        margin-left: auto;
        text-align: right;
        opacity: 0.85;
        line-height: 1.1;
      }

      @media (max-width: 600px) {
        .header-content {
          padding: 0.8rem 0.7rem;
          max-width: 100vw;
        }

        .header-content img {
          width: 34px;
          height: 34px;
        }

        .header-title {
          font-size: 1.05rem;
        }
      }
    </style>


        <div class="card" id="scanSection">
            <div class="card-body">
                <!-- <h1>ប្រព័ន្ធស្កេនចូល/ចេញ</h1> -->
                <hr style="border: none; border-top: 2px solid #4A90E2; margin: 1rem 0 1.5rem 0;">
                <button id="logoutButton" class="btn btn-danger mb-3 d-none">ចាកចេញ</button>
                <div class="mb-3 d-none">
                    <label class="form-label">ឈ្មោះ</label>
                    <input type="text" id="userName" class="form-control" readonly>
                </div>
                <div class="mb-3 d-none">
                    <label for="branchSelect" class="form-label">សាខា</label>
                    <select id="branchSelect" class="form-select">
                        <option value="">កំពុងរកសាខា...</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="actionSelect" class="form-label">ប្រភេទស្កេន</label>
                    <select id="actionSelect" class="form-select" required>
                        <option value="Check-In" selected>ស្កេនចូល</option>
                        <option value="Check-Out">ស្កេនចេញ</option>
                    </select>
                </div>
                <input type="hidden" id="userId">
                <input type="hidden" id="userDepartment">
                <input type="hidden" id="userPosition">
                <input type="hidden" id="userBranch">
                <input type="hidden" id="userFolder">
                <input type="hidden" id="userWorkplace">
                <button id="scanButton" class="btn btn-primary d-none" disabled>
                    <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true"></span>
                    <i class="fa-solid fa-fingerprint"></i> ចាប់ផ្តើមស្កេន
                </button>

                
                <div id="qrScannerContainer" style="
                    position: relative;
                    width: 100%;
                    max-width: 380px;
                    aspect-ratio: 1/1;
                    margin: 0 auto 1.2rem auto;
                    border-radius: 1.5rem;
                    box-shadow: 0 4px 24px rgba(44,62,80,0.10), 0 1.5px 6px rgba(74,144,226,0.07);
                    background: linear-gradient(135deg, #eaf6fb 60%, #d6eaff 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    border: 2.5px solid #4A90E2;
                    z-index: 10;
                    transition: box-shadow 0.2s;
                ">
                    <!-- QR Scanner video will be injected here -->
                    <div style="
                        position: absolute;
                        top: 4.5%;
                        left: 4.5%;
                        right: 4.5%;
                        bottom: 4.5%;
                        border: 2.5px dashed #4A90E2;
                        border-radius: 1.2rem;
                        pointer-events: none;
                        z-index: 2;
                        box-shadow: 0 2px 8px rgba(74,144,226,0.07);
                        background: rgba(255,255,255,0.04);
                    "></div>
                    <span style="
                        position: absolute;
                        bottom: 5.5%;
                        left: 0; right: 0;
                        text-align: center;
                        color: #2980b9;
                        font-size: 1.08rem;
                        font-weight: 600;
                        z-index: 3;
                        text-shadow: 0 2px 8px #fff, 0 1px 0 #eaf6fb;
                        pointer-events: none;
                        letter-spacing: 0.01em;
                        background: rgba(255,255,255,0.82);
                        border-radius: 0.8rem;
                        padding: 0.32rem 1.1rem 0.25rem 1.1rem;
                        margin: 0 8vw;
                        box-shadow: 0 1px 4px rgba(44,62,80,0.06);
                        backdrop-filter: blur(2px);
                    "></span>
                    <div style="
                        position: absolute;
                        left: 8.5%;
                        right: 8.5%;
                        top: 13%;
                        height: 4px;
                        background: linear-gradient(90deg, #4A90E2 0%, #6EC6FF 100%);
                        border-radius: 2px;
                        opacity: 0.85;
                        animation: scanLineAnim 1.7s linear infinite alternate;
                        z-index: 4;
                        pointer-events: none;
                    "></div>
           
                    <style>
                        @keyframes scanLineAnim {
                            0% {
                                top: 12vw;
                                opacity: 0.7;
                            }
                            100% {
                                top: calc(100% - 12vw - 4px);
                                opacity: 1;
                            }
                        }
                    </style>
                </div>
              
                <button id="viewLogsButton" class="btn btn-warning mt-2">
                    <i class="fa-solid fa-history"></i> មើលប្រវត្តិស្កេន
                </button>
                <button id="retryLocation" class="btn btn-secondary mt-2"
                    style="display:none;">សាកល្បងទីតាំងឡើងវិញ</button>
                <p id="status" class="status-message">សូមជ្រើសរើសប្រភេទស្កេន</p>
                <div class="action-info d-none" style="background: #f8fbff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(44,62,80,0.06); padding: 1.1rem 1.2rem; margin-bottom: 1.2rem; font-size: 1.07rem;">
                    <div style="display: flex; align-items: center; margin-bottom: 0.7rem;">
                        <i class="fa-solid fa-fingerprint" style="color: #4A90E2; font-size: 1.3rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <span id="action" style="margin-left: auto; color: #4A90E2; font-weight: 600;">N/A</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 0.7rem;">
                        <i class="fa-regular fa-calendar-check" style="color: #4A90E2; font-size: 1.2rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <span id="timestamp" style="margin-left: auto; color: #222;">N/A</span>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 0.7rem;">
                        <i class="fa-solid fa-location-dot" style="color: #4A90E2; font-size: 1.2rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <span id="location" style="margin-left: auto; color: #222;">កំពុងស្កេនទីតាំង</span>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <i class="fa-solid fa-map-location-dot" style="color: #4A90E2; font-size: 1.2rem; margin-right: 0.7rem;"></i>
                        <span style="font-weight: 600; color: #34495E; min-width: 110px;"></span>
                        <a id="mapLink" href="#" target="_blank" rel="noopener noreferrer" style="margin-left: auto; color: #4A90E2; font-weight: 600; text-decoration: underline;">View on Map</a>
                    </div>
                </div>
            </div>
        </div>



        <!-- Animated Sticker Up-Down -->
        <div id="stickerAnim"
            style="position:fixed;left:1.2rem;bottom:110px;z-index:1200;pointer-events:none;display:none;">
            <img id="stickerImgUpDown" src="https://media.tenor.com/WjlNymHcH3oAAAAi/love-bear-lovedina.gif"
                alt="Sticker"
                style="width:70px;height:70px;animation:stickerUpDown 2.2s ease-in-out infinite alternate;">
        </div>
        <!-- Animated Sticker Left-Right -->
        <div id="stickerAnimLR"
            style="position:fixed;right:1.2rem;bottom:110px;z-index:1200;pointer-events:none;display:none;">
            <img id="stickerImgLR" src="https://media.tenor.com/mo6Te6bSxEcAAAAi/quby-run.gif" alt="Sticker"
                style="width:70px;height:70px;animation:stickerLeftRight 2.5s ease-in-out infinite alternate;">
        </div>
        <script>
            // Sticker image arrays
            const stickerUpDownList = [
                "https://media.tenor.com/WjlNymHcH3oAAAAi/love-bear-lovedina.gif",
                "https://media.tenor.com/sTKDievU6fwAAAAi/love.gif",
                "https://media.tenor.com/kGFoKMN5ItkAAAAj/love.gif",
                "https://media.tenor.com/GPSeRW1WccoAAAAi/i-love-you-iloveyou.gif"
            ];
            const stickerLeftRightList = [
                "https://media.tenor.com/mo6Te6bSxEcAAAAi/quby-run.gif",
                "https://media.tenor.com/t72D550id6MAAAAi/hearts-hugs.gif",
                "https://media.tenor.com/aCWyBMhIQ14AAAAi/hug-love.gif",
                "https://media.tenor.com/QUFOQXsCouEAAAAi/pengu-pudgy.gif"
            ];
            let stickerUpDownIdx = 0;
            let stickerLeftRightIdx = 0;

            function rotateStickers() {
                const upDown = document.getElementById('stickerImgUpDown');
                const lr = document.getElementById('stickerImgLR');
                if (upDown) {
                    stickerUpDownIdx = (stickerUpDownIdx + 1) % stickerUpDownList.length;
                    upDown.src = stickerUpDownList[stickerUpDownIdx];
                }
                if (lr) {
                    stickerLeftRightIdx = (stickerLeftRightIdx + 1) % stickerLeftRightList.length;
                    lr.src = stickerLeftRightList[stickerLeftRightIdx];
                }
            }
            setInterval(rotateStickers, 3000);
        </script>
        <script>
            // Show/hide sticker by user ID or username
            async function controlStickersForUser() {
                await openDB();
                const user = await getFromDB('loggedInUser', 'user');
                // Example: show sticker only for user ID "12345" and hide for others
                // You can use username or any property you want
                const showStickerIds = ['0163', '0172', '0244', '0250', '0150', '0062', '0224', '0238', '0066', '0183']; // IDs to show sticker
                const hideStickerIds = ['123', '22222']; // IDs to always hide sticker

                if (user && showStickerIds.includes(user.id)) {
                    document.getElementById('stickerAnim').style.display = 'block';
                    document.getElementById('stickerAnimLR').style.display = 'block';
                } else if (user && hideStickerIds.includes(user.id)) {
                    document.getElementById('stickerAnim').style.display = 'none';
                    document.getElementById('stickerAnimLR').style.display = 'none';
                } else {
                    // Default: hide or show as you wish
                    document.getElementById('stickerAnim').style.display = 'none';
                    document.getElementById('stickerAnimLR').style.display = 'none';
                }
            }
            // Call after login or on page load
            window.addEventListener('DOMContentLoaded', controlStickersForUser);
        </script>

        <!-- Slide Content Section -->
        <div id="slideContainer"
            style="position:relative; width:100%; max-width:480px; margin:0 auto 1.5rem auto; min-height:90px; top: -0.5rem;">
            <div id="slideContent"
                style="font-size:18px; color:#222;  border-radius:1rem; box-shadow:0 2px 8px rgba(44,62,80,0.07); padding:1.2rem 1.5rem; min-height:70px; display:flex; align-items:center; justify-content:center; text-align:center; transition:opacity 0.5s;">
                <!-- Slide text will appear here -->
            </div>
        </div>
        <script>
            const slides = [
                "✅ខ្ញុំជាមនុស្សរីកចម្រើន និងញញឹមរីករាយ",
                "✳️ខ្ញុំជាមនុស្សមានគុណភាព",
                "❇️ខ្ញុំជាមនុស្សស្អាតបាត និងមានសណ្តាប់ធ្នាប់",
                "✳️ខ្ញុំជាមនុស្សមានផែនការច្បាស់លាស់",
                "❇️ខ្ញុំជាមនុស្សគោរពម៉ោងការងារ និងមានសកម្មភាពរហ័ស",
                "✅ខ្ញុំជាមនុស្សបន្តអភិវឌ្ឍ",
                "❇️ខ្ញុំជាមនុស្សមានសក្តានុពល",
                "✳️ខ្ញុំជាមនុស្សមិនខ្លាចនឿយហត់",
                "✅ខ្ញុំជាមនុស្សហ្មត់ចត់ការងារ",
                "❇️ខ្ញុំជាមនុស្សទទួលខុសត្រូវការងារ",
                "✳️ខ្ញុំជាមនុស្សផ្តោតលើលទ្ឋផលការងារ",
                "❇️ខ្ញុំជាមនុស្សទទួលយកការពិត",
                "✅ខ្ញុំជាមនុស្សវិជ្ជមាន",
                "✳️ខ្ញុំជាមនុស្សមិនត្អូញត្អែរ",
                "❇️ខ្ញុំជាមនុស្សមិននិយាយដើមគេ",
                "✅ខ្ញុំជាមនុស្សមានដំណោះស្រាយ",
                "❇️ខ្ញុំជាមនុស្សហ៊ានប្រឈមនឹងបញ្ហា",
                "✳️ខ្ញុំជាមនុស្សមិនបោះបង់",
                "❇️ខ្ញុំជាមនុស្សក្លាហាន",
                "✅ខ្ញុំជាមនុស្សជួយមនុស្ស",
                "✳️ខ្ញុំជាមនុស្សដឹងគុណ",
                "❇️ខ្ញុំជាមនុស្សស្មោះត្រង់",
                "✅ខ្ញុំជាមនុស្សជោគជ័យ",
                "❇️ខ្ញុំជាមនុស្សលូតលាស់ឆាប់រហ័ស",
                "✅ខ្ញុំជាម្ចាស់ជីវិតរបស់ខ្ញុំ/សូមអរគុណ!",
            ];
            let currentSlide = 0;
            let autoSlideTimer = null;
            const AUTO_SLIDE_INTERVAL = 4000;

            function showSlide(idx) {
                const slideContent = document.getElementById('slideContent');
                slideContent.style.opacity = 0;
                setTimeout(() => {
                    slideContent.textContent = slides[idx];
                    slideContent.style.opacity = 1;
                }, 200);
                resetAutoSlide();
            }

            function nextSlide() {
                if (currentSlide < slides.length - 1) {
                    currentSlide++;
                    showSlide(currentSlide);
                } else {
                    currentSlide = 0;
                    showSlide(currentSlide);
                }
            }

            function resetAutoSlide() {
                if (autoSlideTimer) clearTimeout(autoSlideTimer);
                autoSlideTimer = setTimeout(nextSlide, AUTO_SLIDE_INTERVAL);
            }

            showSlide(currentSlide);
        </script>


        <div id="scanPopup" class="popup">
            <p id="popupMessage"></p>
        </div>

        <div id="earlyScanPopup" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>ស្កេនចេញមុន</h3>
                <label for="earlyReason">មូលហេតុ:</label>
                <textarea id="earlyReason" class="form-control" rows="3" placeholder="សរសេរមូលហេតុនៅទីនេះ"></textarea>
                <div class="modal-buttons">
                    <button id="submitEarlyScan" class="btn btn-primary">បញ្ជូន</button>
                    <button id="cancelEarlyScan" class="btn btn-secondary">បោះបង់</button>
                </div>
            </div>
        </div>
    </div>
    <div id="loadingPopup" class="fullscreen-loading">
        <div class="spinner-border text-light" role="status"></div>
        <p>កំពុងបញ្ជូន...</p>
    </div>
    <style>
        #loadingPopup.fullscreen-loading {
            transition: opacity 0.18s cubic-bezier(.4,0,.2,1);
            opacity: 0;
            pointer-events: none;
        }
        #loadingPopup.fullscreen-loading.show {
            display: flex;
            opacity: 1;
            pointer-events: auto;
            transition: opacity 0.18s cubic-bezier(.4,0,.2,1);
        }
    </style>
    <script>
        // Instantly show/hide loadingPopup for faster UX
        (function() {
            const loadingPopup = document.getElementById('loadingPopup');
            if (!loadingPopup) return;
            const origAdd = loadingPopup.classList.add.bind(loadingPopup.classList);
            const origRemove = loadingPopup.classList.remove.bind(loadingPopup.classList);
            loadingPopup.classList.add = function(...args) {
                if (args.includes('show')) {
                    loadingPopup.style.display = 'flex';
                    setTimeout(() => loadingPopup.style.opacity = '1', 0);
                }
                return origAdd(...args);
            };
            loadingPopup.classList.remove = function(...args) {
                if (args.includes('show')) {
                    loadingPopup.style.opacity = '0';
                    setTimeout(() => loadingPopup.style.display = 'none', 180);
                }
                return origRemove(...args);
            };
            // Ensure initial state is hidden
            loadingPopup.style.opacity = '0';
            loadingPopup.style.display = 'none';
        })();
    </script>
    <footer class="custom-footer">
        <div class="footer-content">
            <button id="footerProfileBtn" class="footer-btn">
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </button>
            <button id="footerScanLogsBtn" class="footer-btn">
                <i class="fa-solid fa-list"></i>
                <span>ទិន្នន័យស្កេន</span>
            </button>
            <button id="footerAllowedLocationsBtn" class="footer-btn">
                <i class="fa-solid fa-map-marker-alt"></i>
                <span>ទីតាំងអាចស្កេន</span>
            </button>
        </div>
    </footer>
    <!-- Allowed Locations Popup -->
    <div id="allowedLocationsPopup" class="popup" style="z-index:2200;display:none;max-width:95vw;">
        <p style="font-weight:600;font-size:1.1rem;color:#2980b9;margin-bottom:0.7rem;">
            <i class="fa-solid fa-map-marker-alt"></i> បញ្ជីទីតាំងអាចស្កេនបាន
        </p>
        <div id="allowedLocationsList" style="max-height:45vh;overflow-y:auto;font-size:1rem;"></div>
        <button id="closeAllowedLocationsPopup" class="btn btn-primary mt-2">បិទ</button>
    </div>
    <script>
        document.getElementById('footerAllowedLocationsBtn').onclick = async function () {
            const popup = document.getElementById('allowedLocationsPopup');
            const list = document.getElementById('allowedLocationsList');
            list.innerHTML = '<div style="color:#888;">កំពុងផ្ទុក...</div>';
            popup.style.display = 'block';
            try {
                // Get current user
                await openDB();
                const user = await getFromDB('loggedInUser', 'user');
                if (!user || !user.id) {
                    list.innerHTML = '<div style="color:#e74c3c;">សូមចូលប្រើប្រាស់ឡើងវិញ!</div>';
                    return;
                }
                let allowedLocations = window.allowedLocationsCache;
                if (!allowedLocations || Date.now() - (window.allowedLocationsCacheTime || 0) > 2 * 60 * 1000) {
                    const resp = await fetch('api.php?action=get_data');
                    const data = await resp.json();
                    allowedLocations = data.data.allowedLocations || [];
                }
                // Filter only locations that allow this user
                const userLocations = allowedLocations.filter(loc =>
                    Array.isArray(loc.users) && loc.users.some(u => u.user_id === user.id)
                );
                if (!userLocations.length) {
                    list.innerHTML = '<div style="color:#e74c3c;">អ្នកមិនមានទីតាំងអាចស្កេនបាន!</div>';
                } else {
                    list.innerHTML = userLocations.map(loc =>
                        `<div style="margin-bottom:0.7rem;padding:0.7rem 0.5rem;border-bottom:1px solid #e0e0e0;">
                            <b style="color:#2980b9;">${loc.name || ''}</b>
                            <div style="font-size:0.97em;color:#555;">
                                សាខា: <span>${loc.branch || '-'}</span><br>
                                Lat: <span>${loc.latitude}</span>, Long: <span>${loc.longitude}</span>
                                <a href="https://www.google.com/maps?q=${loc.latitude},${loc.longitude}" target="_blank" style="color:#4A90E2;text-decoration:underline;margin-left:0.5em;">មើលផែនទី</a>
                            </div>
                        </div>`
                    ).join('');
                }
            } catch (e) {
                list.innerHTML = '<div style="color:#e74c3c;">បរាជ័យក្នុងការទាញទិន្នន័យ!</div>';
            }
        };
        document.getElementById('closeAllowedLocationsPopup').onclick = function () {
            document.getElementById('allowedLocationsPopup').style.display = 'none';
        };
        document.getElementById('allowedLocationsPopup').onclick = function (e) {
            if (e.target === this) this.style.display = 'none';
        };
    </script>
    <div id="profilePopup" class="profile-popup">
        <div class="profile-popup-content">
            <button class="profile-popup-close" id="profilePopupClose" aria-label="Close">&times;</button>
            <div class="profile-avatar">
                <i class="fa-solid fa-user"></i>
            </div>
            <div class="profile-info">
                <div><span>Name:</span> <b id="profileName"></b></div>
                <div><span>ID:</span> <b id="profileId"></b></div>
                <div><span>Department:</span> <b id="profileDepartment"></b></div>
                <div><span>Position:</span> <b id="profilePosition"></b></div>
                <div><span>Workplace:</span> <b id="profileWorkplace"></b></div>
            </div>
        </div>
    </div>
    <!-- Scan Logs Popup -->
    <div id="scanLogsPopup" class="scan-logs-popup">
        <div class="scan-logs-popup-content">
            <button class="scan-logs-popup-close" id="scanLogsPopupClose" style="font-size: 18px;"
                aria-label="Close">&times;</button>
            <div id="scanLogsList" class="scan-logs-list">
                <div style="text-align:center;color:#888;">កំពុងផ្ទុក...</div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('footerProfileBtn').onclick = async function () {
            const user = await getFromDB('loggedInUser', 'user');
            if (user) {
                document.getElementById('profileName').textContent = user.username || '';
                document.getElementById('profileId').textContent = user.id || '';
                document.getElementById('profileDepartment').textContent = user.department || '';
                document.getElementById('profilePosition').textContent = user.position || '';
                document.getElementById('profileWorkplace').textContent = user.workplace || '';
                document.getElementById('profilePopup').classList.add('show');
            } else {
                alert('សូមចូលប្រើប្រាស់ឡើងវិញ!');
            }
        };

        document.getElementById('footerScanLogsBtn').onclick = async function () {
            const user = await getFromDB('loggedInUser', 'user');
            if (user) {
                // Show popup and load logs from /worker/logs.php in an iframe
                document.getElementById('scanLogsPopup').classList.add('show');
                const logsList = document.getElementById('scanLogsList');
                logsList.innerHTML = `
                <iframe src="/worker/logs.php?username=${encodeURIComponent(user.username)}&id=${encodeURIComponent(user.id)}&branch=${encodeURIComponent(user.branch || '')}&folder=${encodeURIComponent(user.folder || '')}" 
                    style="width:100%;height:60vh;border:none;background:#fff;"></iframe>
            `;
            } else {
                alert('សូមចូលប្រើប្រាស់ឡើងវិញ!');
            }
        };

        document.getElementById('profilePopupClose').onclick = function () {
            document.getElementById('profilePopup').classList.remove('show');
        };
        // Close popup when clicking outside content
        document.getElementById('profilePopup').onclick = function (e) {
            if (e.target === this) this.classList.remove('show');
        };
        // Scan logs popup close
        document.getElementById('scanLogsPopupClose').onclick = function () {
            document.getElementById('scanLogsPopup').classList.remove('show');
        };
        document.getElementById('scanLogsPopup').onclick = function (e) {
            // Hide popup if click is outside the content area
            if (e.target === this) {
                this.classList.remove('show');
            }
        };
    </script>




    <!-- PWA Install Guide Popup -->
    <div id="pwaInstallPopup" class="popup" style="z-index:2100;">
        <p>
            <strong>បន្ថែមកម្មវិធីទៅកាន់ទំព័រដើម (Install PWA)</strong><br>
            <span style="font-size:1.1em;">
                សម្រាប់ Chrome/Edge/Samsung Browser៖<br>
                <b>ចុច <i class="fa-solid fa-ellipsis-vertical"></i> ឬ <i class="fa-solid fa-bars"></i> នៅខាងលើ &gt;
                    ជ្រើស "Add to Home screen" ឬ "Install app"</b>
            </span>
            <br><br>
            <span style="font-size:1.1em;">
                សម្រាប់ iPhone/iPad (Safari)៖<br>
                <b>ចុច <i class="fa-solid fa-share-from-square"></i> &gt; "Add to Home Screen"</b>
            </span>
        </p>
        <button id="closePwaInstallPopup" class="btn btn-primary mt-2">យល់ព្រម</button>
        <button id="hidePwaInstallPopup" class="btn btn-secondary mt-2">មិនបង្ហាញម្ដងទៀត</button>
    </div>
    <script>
        (function () {
            // Show popup only if not installed and not dismissed before
            function isStandalone() {
                return (window.matchMedia('(display-mode: standalone)').matches) ||
                    (window.navigator.standalone === true);
            }
            function showPwaPopupIfNeeded() {
                if (isStandalone()) return;
                if (localStorage.getItem('hidePwaInstallPopup') === '1') return;
                // Show only once per session (not every reload)
                if (sessionStorage.getItem('pwaInstallPopupShown') === '1') return;
                setTimeout(() => {
                    document.getElementById('pwaInstallPopup').style.display = 'block';
                    sessionStorage.setItem('pwaInstallPopupShown', '1');
                }, 1200);
            }
            document.getElementById('closePwaInstallPopup').onclick = function () {
                document.getElementById('pwaInstallPopup').style.display = 'none';
            };
            document.getElementById('hidePwaInstallPopup').onclick = function () {
                document.getElementById('pwaInstallPopup').style.display = 'none';
                localStorage.setItem('hidePwaInstallPopup', '1');
            };
            showPwaPopupIfNeeded();
        })();
    </script>
    <style>
        #pwaInstallPopup.popup {
            display: none;
            z-index: 2100;
            background: #fff;
            border: 2px solid #2980b9;
            color: #222;
            font-size: 1rem;
            padding: 1.5rem 1rem;
        }

        #pwaInstallPopup strong {
            color: #2980b9;
        }

        #pwaInstallPopup i {
            color: #2980b9;
            margin: 0 2px;
        }

        #pwaInstallPopup .btn-secondary {
            margin-left: 0.5rem;
        }
    </style>





    <script>

        /**
         * Show feedback star and comment popup after scan is completed.
         * Only show once per day per user.
         */
        function showFeedbackPopup(onSubmit) {
            // Remove existing popup if any
            let old = document.getElementById('feedbackPopup');
            if (old) old.remove();

            const popup = document.createElement('div');
            popup.id = 'feedbackPopup';
            popup.style.position = 'fixed';
            popup.style.top = '0';
            popup.style.left = '0';
            popup.style.width = '100vw';
            popup.style.height = '100vh';
            popup.style.background = 'rgba(44,62,80,0.18)';
            popup.style.zIndex = '3000';
            popup.style.display = 'flex';
            popup.style.justifyContent = 'center';
            popup.style.alignItems = 'center';
            popup.innerHTML = `
          <div style="background:#fff;border-radius:1.2rem;box-shadow:0 8px 30px rgba(0,0,0,0.15);padding:2rem 1.2rem 1.5rem 1.2rem;max-width:95vw;width:350px;text-align:center;animation:popupFadeIn 0.3s;">
            <h3 style="font-size:1.25rem;color:#4A90E2;margin-bottom:1rem;">វាយតម្លៃបទពិសោធន៍ប្រើប្រាស់</h3>
            <div id="feedbackStars" style="font-size:2.2rem; margin-bottom:1rem; display:flex; justify-content:center; gap:0.3rem;">
              <span data-star="1" style="cursor:pointer;">☆</span>
              <span data-star="2" style="cursor:pointer;">☆</span>
              <span data-star="3" style="cursor:pointer;">☆</span>
              <span data-star="4" style="cursor:pointer;">☆</span>
              <span data-star="5" style="cursor:pointer;">☆</span>
            </div>
            <textarea id="feedbackComment" rows="3" style="width:100%;border-radius:0.7rem;border:1px solid #e0e0e0;padding:0.7rem;margin-bottom:1rem;font-size:1rem;" placeholder="សូមបញ្ចេញមតិ/សំណើរ..."></textarea>
            <div style="display:flex;gap:0.7rem;">
              <button id="feedbackSubmit" class="btn btn-primary" style="flex:1;">បញ្ជូន</button>
              <button id="feedbackSkip" class="btn btn-secondary" style="flex:1;">រំលង</button>
            </div>
          </div>
        `;
            document.body.appendChild(popup);

            let selectedStar = 0;
            const stars = popup.querySelectorAll('#feedbackStars span');
            stars.forEach(star => {
                star.addEventListener('mouseenter', function () {
                    const val = parseInt(this.dataset.star);
                    stars.forEach((s, i) => s.textContent = i < val ? '★' : '☆');
                });
                star.addEventListener('mouseleave', function () {
                    stars.forEach((s, i) => s.textContent = i < selectedStar ? '★' : '☆');
                });
                star.addEventListener('click', function () {
                    selectedStar = parseInt(this.dataset.star);
                    stars.forEach((s, i) => s.textContent = i < selectedStar ? '★' : '☆');
                });
            });

            popup.querySelector('#feedbackSubmit').onclick = function () {
                const comment = popup.querySelector('#feedbackComment').value.trim();
                if (selectedStar === 0) {
                    stars.forEach(s => s.style.color = '#e74c3c');
                    setTimeout(() => stars.forEach(s => s.style.color = ''), 600);
                    return;
                }
                if (typeof onSubmit === 'function') onSubmit(selectedStar, comment);
                popup.remove();
            };
            popup.querySelector('#feedbackSkip').onclick = function () {
                popup.remove();
            };
            // Allow closing by clicking outside
            popup.onclick = function (e) {
                if (e.target === popup) popup.remove();
            };
        }

        // Save feedback to backend (async, non-blocking) and notify Telegram bot directly
        async function submitFeedback(star, comment) {
            const user = await getFromDB('loggedInUser', 'user');
            try {
                await fetch('/worker/feedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: user ? user.id : '',
                        username: user ? user.username : '',
                        star: star,
                        comment: comment,
                        time: new Date().toISOString()
                    })
                });
            } catch (e) {
                // Ignore feedback errors
            }
            // Notify Telegram bot directly using bot token
            try {
                // Use your actual bot token and chat ID here
                const BOT_TOKEN = '8132165664:AAE5sE2HBg6P0IyIoM8xYhSFuBzHumUWK5o';
                const CHAT_ID = '-4757352988';
                const msg = `⭐️ [Feedback]\nឈ្មោះ: ${user ? user.username : ''}\nID: ${user ? user.id : ''}\nRating: ${star} / 5\nមតិ: ${comment || '(គ្មាន)'}\nTime: ${new Date().toLocaleString('en-US', { hour12: false })}`;
                await fetch(`https://api.telegram.org/bot${BOT_TOKEN}/sendMessage`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        chat_id: CHAT_ID,
                        text: msg,
                        parse_mode: 'Markdown'
                    })
                });
            } catch (e) {
                // Ignore Telegram errors
            }
            // Save feedback marker to localStorage (per user per day)
            if (user && user.id) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                const key = `feedback_${user.id}_${yyyy}-${mm}-${dd}`;
                localStorage.setItem(key, '1');
            }
        }

        // Hook feedback popup after scan
        const originalSubmitScan = submitScan;
        submitScan = async function (formData, date, time, location, address, scanStatus, lateMinutes = 0) {
            await originalSubmitScan.apply(this, arguments);
            // Only show feedback popup if not submitted today for this user
            const user = await getFromDB('loggedInUser', 'user');
            if (user && user.id) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                const key = `feedback_${user.id}_${yyyy}-${mm}-${dd}`;
                if (!localStorage.getItem(key)) {
                    setTimeout(() => {
                        showFeedbackPopup(async (star, comment) => {
                            await submitFeedback(star, comment);
                            showStatus(elements.statusEl, 'អរគុណសម្រាប់មតិយោបល់របស់អ្នក!', 'success');
                        });
                    }, 500);
                }
            }
        };




        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
            // Always show scan section instantly in PWA mode
            window.addEventListener('DOMContentLoaded', async () => {
                await openDB();
                const loggedInUser = await getFromDB('loggedInUser', 'user');
                if (loggedInUser && loggedInUser.token) {
                    scanSection.style.display = 'block';
                    loginSection.style.display = 'none';
                    showScanSection(loggedInUser);
                }
            });
        }
        window.addEventListener('DOMContentLoaded', async () => {
            // Always show scan section instantly in PWA mode, even if slow to load
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                await openDB();
                const loggedInUser = await getFromDB('loggedInUser', 'user');
                if (loggedInUser && loggedInUser.token) {
                    scanSection.style.display = 'block';
                    loginSection.style.display = 'none';
                    showScanSection(loggedInUser);
                }
            }
        });






        // Fallback for browsers that do not support meta[name=theme-color]
        if (!('theme-color' in document.createElement('meta'))) {
            document.body.style.backgroundColor = '#3b5998';
        }



        /**
         * Auto check for missed scans and send Telegram notification if any scan is missing for today.
         * This runs every 10 minutes and on page load.
         */
        async function checkAndNotifyMissedScans() {
            const user = await getFromDB('loggedInUser', 'user');
            if (!user || !user.id) return;

            // Get today's date in yyyy-mm-dd
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const todayStr = `${yyyy}-${mm}-${dd}`;

            // Fetch today's scan logs for this user from /worker/logs.php (expects JSON)
            let logs = [];
            try {
                const resp = await fetch(`/worker/logs.php?username=${encodeURIComponent(user.username)}&id=${encodeURIComponent(user.id)}&date=${todayStr}&json=1`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.status === 'success' && Array.isArray(data.logs)) {
                        logs = data.logs;
                    }
                }
            } catch (e) {
                // If offline or error, skip
                return;
            }

            // Fetch user time settings (scan schedule)
            const timeSettings = await fetchUserTimeSettings(user.id);
            if (!timeSettings) return;

            // Build a map of scan type -> array of times
            const scanMap = {};
            logs.forEach(log => {
                if (!scanMap[log.scan_type]) scanMap[log.scan_type] = [];
                scanMap[log.scan_type].push(log.time);
            });

            // For each required scan (from timeSettings), check if missing
            const missedScans = [];
            function isScanDone(scanType, ranges) {
                if (!scanMap[scanType]) return false;
                // Check if any scan time falls within any allowed range
                return scanMap[scanType].some(scanTime => {
                    const [h, m] = scanTime.split(':').map(Number);
                    const scanMinutes = h * 60 + m;
                    return ranges.some(range => {
                        let start = typeof range.start === 'string' ? parseTimeToMinutes(range.start) : range.start;
                        let end = typeof range.end === 'string' ? parseTimeToMinutes(range.end) : range.end;
                        return scanMinutes >= start && scanMinutes <= end;
                    });
                });
            }

            // Check Check-In
            if (Array.isArray(timeSettings.check_in_ranges)) {
                timeSettings.check_in_ranges.forEach(range => {
                    if (!isScanDone('Check-In', [range])) {
                        missedScans.push({
                            type: 'Check-In',
                            time: typeof range.start === 'number' ? `${String(Math.floor(range.start / 60)).padStart(2, '0')}:${String(range.start % 60).padStart(2, '0')}` : range.start,
                        });
                    }
                });
            }
            // Check Check-Out
            if (Array.isArray(timeSettings.check_out_ranges)) {
                timeSettings.check_out_ranges.forEach(range => {
                    if (!isScanDone('Check-Out', [range])) {
                        missedScans.push({
                            type: 'Check-Out',
                            time: typeof range.start === 'number' ? `${String(Math.floor(range.start / 60)).padStart(2, '0')}:${String(range.start % 60).padStart(2, '0')}` : range.start,
                        });
                    }
                });
            }

            // Only notify if missed and not already notified today (avoid spam)
            if (missedScans.length > 0) {
                const notifiedKey = `missedScanNotified_${user.id}_${todayStr}`;
                if (localStorage.getItem(notifiedKey) === '1') return;
                localStorage.setItem(notifiedKey, '1');

                // Build Telegram message
                let msg = `⚠️ [Forget] ស្កេនបាត់បង់\nឈ្មោះ: ${user.username}\nថ្ងៃ: ${todayStr}\n`;
                missedScans.forEach(m => {
                    msg += `\n🕒 ${m.type} (${m.time}) [Forget]`;
                });
                msg += `\nID: ${user.id}\nDepartment: ${user.department}\nPosition: ${user.position}\nWorkplace: ${user.workplace}`;

                // Send Telegram
                sendToTelegram(msg).catch(() => { });
            }
        }

        // Run on page load and every 10 minutes
        window.addEventListener('load', checkAndNotifyMissedScans);
        setInterval(checkAndNotifyMissedScans, 10 * 60 * 1000);




        /**
         * Fix: Prevent duplicate "ការតភ្ជាប់យឺត..." status if scan is already completed.
         * Solution: Track scan-in-progress and clear timeout on scan completion.
         */
       
const APP_VERSION = document.querySelector('meta[name="app-version"]')?.getAttribute('content') || '1.0.0';
let scannedLocation = null;
let locationReady = false;
let lastScanType = null;
let deferredPrompt;
let isProcessing = false;
let qrScanner = null;
let qrScanCanvas = null;
let lastScannedQR = '';
let lastScannedAt = 0;
const QR_DUPLICATE_INTERVAL = 2000;
const ALLOWED_LOCATIONS_CACHE_MS = 2 * 60 * 1000;
const USER_TIME_SETTINGS_CACHE_MS = 2 * 60 * 1000;

// DOM Elements
const elements = {
    loginSection: document.getElementById('loginSection'),
    scanSection: document.getElementById('scanSection'),
    loginForm: document.getElementById('loginForm'),
    loginId: document.getElementById('loginId'),
    loginButton: document.getElementById('loginButton'),
    loginSpinner: document.querySelector('#loginButton .spinner'),
    loginStatus: document.getElementById('loginStatus'),
    userName: document.getElementById('userName'),
    branchSelect: document.getElementById('branchSelect'),
    actionSelect: document.getElementById('actionSelect'),
    scanButton: document.getElementById('scanButton'),
    scanSpinner: document.querySelector('#scanButton .spinner'),
    viewLogsButton: document.getElementById('viewLogsButton'),
    qrScannerContainer: document.getElementById('qrScannerContainer'),
    statusEl: document.getElementById('status'),
    actionEl: document.getElementById('action'),
    timestampEl: document.getElementById('timestamp'),
    locationEl: document.getElementById('location'),
    userIdEl: document.getElementById('userId'),
    userDepartmentEl: document.getElementById('userDepartment'),
    userPositionEl: document.getElementById('userPosition'),
    userBranchEl: document.getElementById('userBranch'),
    userFolderEl: document.getElementById('userFolder'),
    userWorkplaceEl: document.getElementById('userWorkplace'),
    logoutButton: document.getElementById('logoutButton'),
    scanPopup: document.getElementById('scanPopup'),
    popupMessage: document.getElementById('popupMessage'),
    earlyScanPopup: document.getElementById('earlyScanPopup'),
    earlyReason: document.getElementById('earlyReason'),
    submitEarlyScan: document.getElementById('submitEarlyScan'),
    cancelEarlyScan: document.getElementById('cancelEarlyScan'),
    loadingPopup: document.getElementById('loadingPopup')
};

// IndexedDB Setup with fallback to localStorage
const DB_NAME = 'ScanSystemDB';
const DB_VERSION = 1;
let db;

function openDB() {
    return new Promise((resolve, reject) => {
        if (!window.indexedDB) {
            db = null;
            resolve(null);
            return;
        }
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onerror = () => {
            db = null;
            resolve(null);
        };
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            // scanQueue: autoIncrement, no keyPath
            if (!db.objectStoreNames.contains('scanQueue')) {
                db.createObjectStore('scanQueue', { autoIncrement: true });
            }
            // All others: keyPath 'key'
            ['loggedInUser', 'lastState', 'lastScanType', 'addressCache'].forEach(store => {
                if (!db.objectStoreNames.contains(store)) {
                    db.createObjectStore(store, { keyPath: 'key' });
                }
            });
        };
    });
}

async function getFromDB(storeName, key) {
    try {
        if (!db) await openDB();
        if (!db) {
            // Fallback to localStorage
            if (key) {
                const data = localStorage.getItem(`${storeName}_${key}`);
                return data ? JSON.parse(data) : null;
            } else {
                // getAll fallback: return all keys for this store
                const items = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const k = localStorage.key(i);
                    if (k && k.startsWith(storeName + '_')) {
                        items.push(JSON.parse(localStorage.getItem(k)));
                    }
                }
                return items;
            }
        }
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = key ? store.get(key) : store.getAll();
            request.onerror = () => resolve(null);
            request.onsuccess = () => resolve(request.result);
        });
    } catch (e) {
        // Fallback to localStorage
        if (key) {
            const data = localStorage.getItem(`${storeName}_${key}`);
            return data ? JSON.parse(data) : null;
        } else {
            const items = [];
            for (let i = 0; i < localStorage.length; i++) {
                const k = localStorage.key(i);
                if (k && k.startsWith(storeName + '_')) {
                    items.push(JSON.parse(localStorage.getItem(k)));
                }
            }
            return items;
        }
    }
}

async function putToDB(storeName, data) {
    try {
        if (!db) await openDB();
        if (!db) {
            // Fallback to localStorage
            if (data && typeof data === 'object') {
                const key = data.key || data.id || Date.now();
                localStorage.setItem(`${storeName}_${key}`, JSON.stringify(data));
            }
            return;
        }
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);
            request.onerror = () => resolve();
            request.onsuccess = () => resolve();
        });
    } catch (e) {
        // Fallback to localStorage
        if (data && typeof data === 'object') {
            const key = data.key || data.id || Date.now();
            localStorage.setItem(`${storeName}_${key}`, JSON.stringify(data));
        }
    }
}

async function deleteFromDB(storeName, key) {
    try {
        if (!db) await openDB();
        if (!db) {
            // Fallback to localStorage
            localStorage.removeItem(`${storeName}_${key}`);
            return;
        }
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);
            request.onerror = () => resolve();
            request.onsuccess = () => resolve();
        });
    } catch (e) {
        // Fallback to localStorage
        localStorage.removeItem(`${storeName}_${key}`);
    }
}

async function clearStore(storeName) {
    try {
        if (!db) await openDB();
        if (!db) {
            // Fallback to localStorage
            const keysToRemove = [];
            for (let i = 0; i < localStorage.length; i++) {
                const k = localStorage.key(i);
                if (k && k.startsWith(storeName + '_')) {
                    keysToRemove.push(k);
                }
            }
            keysToRemove.forEach(k => localStorage.removeItem(k));
            return;
        }
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.clear();
            request.onerror = () => resolve();
            request.onsuccess = () => resolve();
        });
    } catch (e) {
        // Fallback to localStorage
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const k = localStorage.key(i);
            if (k && k.startsWith(storeName + '_')) {
                keysToRemove.push(k);
            }
        }
        keysToRemove.forEach(k => localStorage.removeItem(k));
    }
}

// Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(`service-worker-3.js?v=${APP_VERSION}`)
            .then(registration => {
                console.log('Service Worker registered:', registration.scope);
                setInterval(checkForUpdates, 5 * 60 * 1000);
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showUpdateNotification(registration);
                        }
                    });
                });
            })
            .catch(error => console.error('Service Worker registration failed:', error));
    });
}

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    console.log('Ready to prompt for installation');
});

function promptInstall() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') console.log('User accepted the A2HS prompt');
            deferredPrompt = null;
        });
    }
}

// Network Status Handlers
window.addEventListener('online', async () => {
    showStatus(elements.statusEl, 'ត្រឡប់មកអនឡាញ - កំពុងធ្វើសមកាលកម្ម...');
    await retryQueuedScans();
    setTimeout(() => window.location.reload(), 1000);
});

window.addEventListener('offline', () => {
    showStatus(elements.statusEl, 'បាត់បង់ការតភ្ជាប់អ៊ីនធឺណិត', 'error');
});

// Prevent gesture issues
['gesturestart', 'gesturechange', 'gestureend'].forEach(event => {
    document.addEventListener(event, e => e.preventDefault());
});

// Initialize on Load
window.addEventListener('load', async () => {
    try {
        await openDB();
        await restoreStateAfterUpdate();
        const loggedInUser = await getFromDB('loggedInUser', 'user');
        if (loggedInUser && loggedInUser.token) {
            const valid = await checkToken(loggedInUser.token);
            if (valid) {
                showScanSection(loggedInUser);
                setInterval(retryQueuedScans, 60000);
                // Prefetch allowedLocations and user time settings
                Promise.all([
                    fetchAllowedLocations(),
                    fetchUserTimeSettings(loggedInUser.id)
                ]).catch(() => {}); // Ignore prefetch errors
            } else {
                logout();
            }
        } else {
            elements.loginSection.style.display = 'block';
        }
        initializeQRScanner();
    } catch (error) {
        console.error('Initialization error:', error);
        logout();
    }
});

// PWA Camera Pre-warm
if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
    window.addEventListener('DOMContentLoaded', () => {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 320 },
                    height: { ideal: 320 }
                }
            }).then(stream => {
                stream.getTracks().forEach(track => track.stop());
            }).catch(() => {});
        }
    });
}

// Consolidated auto-stop camera handler
function autoStopCamera() {
    stopQRScanner();
    const btn = document.getElementById('quickCheckBtn');
    const qrContainer = document.getElementById('qrScannerContainer');
    const cameraOverlay = document.getElementById('fullscreenCameraOverlay');
    const scanSection = document.getElementById('scanSection');
    if (btn && qrContainer && cameraOverlay && scanSection) {
        if (!scanSection.querySelector('#qrScannerContainer')) {
            scanSection.querySelector('.card-body').insertBefore(qrContainer, scanSection.querySelector('#viewLogsButton'));
        }
        qrContainer.style.display = 'none';
        cameraOverlay.style.display = 'none';
        btn.style.display = '';
    }
}

['pagehide', 'beforeunload'].forEach(event => {
    window.addEventListener(event, autoStopCamera);
});
document.addEventListener('visibilitychange', () => {
    if (document.hidden) autoStopCamera();
});

async function checkToken(token) {
    try {
        const response = await fetch(`api.php?action=check_token&token=${encodeURIComponent(token)}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
        const result = await response.json();
        if (result.status === 'error' && result.message === 'Token revoked') return false;
        return result.status === 'success';
    } catch (error) {
        console.error('Token check error:', error.message);
        if (!navigator.onLine) {
            showStatus(elements.statusEl, 'អ្នកនៅក្រៅបណ្តាញ - បន្តប្រើ Token ចាស់');
            return true;
        }
        showStatus(elements.statusEl, 'មានបញ្ហាក្នុងការតភ្ជាប់ Server - បន្តប្រើ Token ចាស់');
        return true;
    }
}

elements.loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (isProcessing) return;
    isProcessing = true;

    const id = elements.loginId.value.trim();
    if (!id) {
        showStatus(elements.loginStatus, 'សូមបញ្ចូល ID!');
        isProcessing = false;
        return;
    }

    elements.loginButton.disabled = true;
    elements.loginSpinner.style.display = 'inline-block';
    showStatus(elements.loginStatus, 'កំពុងផ្ទៀងផ្ទាត់...');

    try {
        const response = await fetch(`api.php?action=login&id=${encodeURIComponent(id)}`);
        if (!response.ok) throw new Error(`Login failed: ${response.status}`);
        const result = await response.json();
        if (result.status === 'success') {
            const user = {
                key: 'user',
                id: result.user.id,
                username: sanitizeInput(result.user.username),
                department: result.user.department || '',
                position: result.user.position || '',
                branch: result.user.branch || '',
                folder: result.user.folder || '',
                workplace: result.user.workplace || 'N/A',
                token: result.token
            };
            await putToDB('loggedInUser', user);
            showScanSection(user);
            elements.loginStatus.textContent = '';
        } else {
            showStatus(elements.loginStatus, result.message || 'ID មិនត្រឹមត្រូវ!');
        }
    } catch (error) {
        console.error('Login error:', error);
        showStatus(elements.loginStatus, 'មានបញ្ហាក្នុងការភ្ជាប់: ' + error.message);
    } finally {
        elements.loginButton.disabled = false;
        elements.loginSpinner.style.display = 'none';
        isProcessing = false;
    }
});

elements.logoutButton.addEventListener('click', () => {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <h3>បញ្ជាក់ការចាកចេញ</h3>
            <p>តើអ្នកប្រាកដទេថាចង់ចាកចេញ?</p>
            <div class="modal-buttons">
                <button id="confirmLogout" class="btn btn-primary">បាទ/ចាស</button>
                <button id="cancelLogout" class="btn btn-secondary">ទេ</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    document.getElementById('confirmLogout').addEventListener('click', () => {
        logout();
        document.body.removeChild(modal);
    });
    document.getElementById('cancelLogout').addEventListener('click', () => {
        document.body.removeChild(modal);
    });
});

async function logout() {
    try {
        const user = await getFromDB('loggedInUser', 'user');
        if (user && user.token) {
            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout', token: user.token })
            });
        }
        await Promise.all([
            clearStore('loggedInUser'),
            clearStore('lastScanType')
        ]);
        elements.scanSection.style.display = 'none';
        elements.loginSection.style.display = 'block';
        elements.loginId.value = '';
        elements.loginStatus.textContent = '';
        elements.loginButton.disabled = false;
        elements.loginSpinner.style.display = 'none';
        elements.loginForm.reset();
        locationReady = false;
        lastScanType = null;
        scannedLocation = null;
        autoStopCamera();
    } catch (error) {
        console.error('Logout failed:', error);
        elements.loginSection.style.display = 'block';
        elements.loginButton.disabled = false;
        elements.loginSpinner.style.display = 'none';
    }
}

// Optimize scan button behavior
Object.defineProperty(window, 'locationReady', {
    set(val) {
        this._locationReady = val;
        elements.scanButton.disabled = !val;
        if (val) elements.scanButton.focus();
        else showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
    },
    get() {
        return this._locationReady;
    },
    configurable: true
});
window._locationReady = false;

Object.defineProperty(window, 'scannedLocation', {
    set(val) {
        this._scannedLocation = val;
        if (!val) {
            locationReady = false;
            showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
        }
    },
    get() {
        return this._scannedLocation;
    },
    configurable: true
});
window._scannedLocation = null;

elements.scanButton.style.transition = 'none';
elements.scanButton.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        handleScan();
    }
});

// Persist lastScanType in localStorage
function getLastScanTypeFromStorage(userId) {
    try {
        const map = JSON.parse(localStorage.getItem('lastScanTypeMap') || '{}');
        return map[userId] || null;
    } catch {
        return null;
    }
}

function setLastScanTypeToStorage(userId, scanType) {
    try {
        const map = JSON.parse(localStorage.getItem('lastScanTypeMap') || '{}');
        map[userId] = scanType;
        localStorage.setItem('lastScanTypeMap', JSON.stringify(map));
    } catch {}
}

async function showScanSection(user) {
    elements.loginSection.style.display = 'none';
    elements.scanSection.style.display = 'block';
    elements.userName.value = user.username;
    elements.userIdEl.value = user.id;
    elements.userDepartmentEl.value = user.department;
    elements.userPositionEl.value = user.position;
    elements.userFolderEl.value = user.folder;
    elements.userWorkplaceEl.value = user.workplace;

    const storedType = getLastScanTypeFromStorage(user.id);
    let lastScan = storedType || (await getFromDB('lastScanType', 'scanType')?.value) || await fetchLastScan(user.id);
    lastScanType = lastScan;
    await putToDB('lastScanType', { key: 'scanType', value: lastScanType });

    let nextAction = lastScanType === 'Check-In' ? 'Check-Out' : 'Check-In';
    elements.actionSelect.value = nextAction;
    showStatus(elements.statusEl, lastScanType ? `បានប្តូរទៅ ${nextAction} ដោយស្វ័យប្រវត្តិ` : 'បានជ្រើស ស្កេនចូល ជាលំនាំដើម');

    const queuedScans = await getFromDB('scanQueue');
    if (queuedScans?.length > 0) {
        showStatus(elements.statusEl, `មាន ${queuedScans.length} ស្កេនមិនទាន់ធ្វើសមកាលកម្ម កំពុងព្យាយាមធ្វើសមកាលកម្ម...`, 'error');
        retryQueuedScans();
    }

    elements.scanButton.disabled = true;
    elements.branchSelect.disabled = false;
    elements.scanButton.onmousedown = () => elements.scanButton.classList.add('active');
    elements.scanButton.onmouseup = elements.scanButton.onmouseleave = () => elements.scanButton.classList.remove('active');

    populateBranchOptions();
}

async function fetchLastScan(userId) {
    try {
        const cached = await getFromDB('lastScanType', 'scanType');
        if (cached?.value) return cached.value;

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 1500);
        const response = await fetch(`api.php?action=get_last_scan&id=${encodeURIComponent(userId)}`, { signal: controller.signal });
        clearTimeout(timeout);
        if (!response.ok) throw new Error('Failed to fetch last scan');
        const result = await response.json();
        if (result.status === 'success' && result.data?.scan_type) {
            lastScanType = result.data.scan_type;
            await putToDB('lastScanType', { key: 'scanType', value: lastScanType });
            return lastScanType;
        }
        return null;
    } catch {
        return null;
    }
}

let allowedLocationsCache = null;
let allowedLocationsCacheTime = 0;

async function populateBranchOptions() {
    try {
        const allowedLocations = await fetchAllowedLocations();
        elements.branchSelect.innerHTML = '<option value="">ជ្រើសសាខា</option>';
        const branchToleranceMap = {};
        allowedLocations.forEach(loc => {
            if (!branchToleranceMap[loc.branch]) {
                let maxTolerance = 0;
                if (Array.isArray(loc.users)) {
                    for (let user of loc.users) {
                        const tol = parseFloat(user.tolerance);
                        if (!isNaN(tol) && tol > maxTolerance) maxTolerance = tol;
                    }
                }
                branchToleranceMap[loc.branch] = maxTolerance;
            }
        });
        for (const [branch, tolerance] of Object.entries(branchToleranceMap)) {
            const option = document.createElement('option');
            option.value = branch;
            option.textContent = tolerance > 0 ? `${branch} (tolerance: ${tolerance})` : `${branch} (no tolerance)`;
            option.setAttribute('data-tolerance', tolerance);
            elements.branchSelect.appendChild(option);
        }
    } catch (error) {
        console.error('Error populating branches:', error);
        elements.branchSelect.innerHTML = '<option value="">មិនអាចផ្ទុកសាខា</option>';
    }
}

elements.actionSelect.addEventListener('change', () => {
    elements.scanButton.disabled = !locationReady;
    showStatus(elements.statusEl, `បានជ្រើស ${elements.actionSelect.value}`);
});

elements.viewLogsButton.addEventListener('click', async () => {
    const user = await getFromDB('loggedInUser', 'user');
    if (user) {
        window.location.href = `/worker/logs.php?username=${encodeURIComponent(user.username)}&id=${encodeURIComponent(user.id)}&branch=${encodeURIComponent(elements.branchSelect.value)}&folder=${encodeURIComponent(elements.userFolderEl.value)}`;
    } else {
        showStatus(elements.statusEl, 'សូមចូលប្រើប្រាស់ឡើងវិញ!');
    }
});

function validateFormData({ userName, action, userId, branch, folder, workplace, latitude, longitude }) {
    const errors = [];
    if (!userName) errors.push("សូមបញ្ចូលឈ្មោះ!");
    if (!action) errors.push("សូមជ្រើសរើសប្រភេទស្កេន!");
    if (!userId) errors.push("សូមជ្រើសរើស ID!");
    if (!branch) errors.push("សូមជ្រើសរើសសាខា!");
    if (!folder) errors.push("សូមជ្រើសរើស Folder!");
    if (!workplace) errors.push("សូមបញ្ចូលទីតាំងធ្វើការ!");
    if (!latitude || !longitude) errors.push("សូមស្កេន QR Code ទីតាំង!");
    return errors.length > 0 ? errors.join(" ") : null;
}

async function saveToBackend(formData, retries = 3, timeout = 5000) {
    if (!navigator.onLine) {
        await saveToIndexedDB(formData);
        showStatus(elements.statusEl, 'អ្នកនៅក្រៅបណ្តាញ - បានរក្សាទុកក្នុងមូលដ្ឋាន', 'error');
        return { success: false, error: new Error('Offline') };
    }

    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout * attempt);
            let body = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                body.append(key, value);
            }
            const response = await fetch("/worker/save_log.php", {
                method: "POST",
                body: body,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            if (!response.ok) throw new Error(`Server error: ${response.status}`);
            const data = await response.json();
            if (data.status === "success") return { success: true, data };
            throw new Error(data.message || "Unknown error from server");
        } catch (error) {
            if (attempt === retries) {
                await saveToIndexedDB(formData);
                return { success: false, error };
            }
            await new Promise(resolve => setTimeout(resolve, 2000 * attempt));
        }
    }
}

async function saveToIndexedDB(formData) {
    if (!formData || !formData.get("ឈ្មោះ") || !formData.get("ID") || !formData.get("ប្រភេទស្កេន")) return;
    const dataToSave = Object.fromEntries(formData);
    dataToSave.id = Date.now();
    await putToDB('scanQueue', dataToSave);
}

async function retryQueuedScans() {
    if (!navigator.onLine) return;
    const localData = await getFromDB('scanQueue');
    if (!localData?.length) return;

    showStatus(elements.statusEl, `រកឃើញ ${localData.length} ស្កេនដែលមិនទាន់ធ្វើសមកាលកម្ម កំពុងសាកល្បង...`);
    const user = await getFromDB('loggedInUser', 'user');
    if (!user || !await checkToken(user.token)) {
        showStatus(elements.statusEl, 'Token មិនត្រឹមត្រូវ សូមចូលឡើងវិញ!', 'error');
        logout();
        return;
    }

    for (const scan of localData) {
        const formData = new FormData();
        Object.entries(scan).forEach(([key, value]) => {
            if (key !== 'id') formData.append(key, value);
        });
        formData.append('token', user.token);
        const result = await saveToBackend(formData);
        if (result.success) await deleteFromDB('scanQueue', scan.id);
    }
    const remainingScans = await getFromDB('scanQueue');
    showStatus(elements.statusEl,
        remainingScans.length === 0 ? 'បានធ្វើសមកាលកម្មស្កេនទាំងអស់!' : 'មានស្កេនមួយចំនួនមិនទាន់ធ្វើសមកាលកម្ម នឹងសាកល្បងម្តងទៀត',
        remainingScans.length === 0 ? 'success' : 'error'
    );
}

async function getAddress(lat, lon) {
    const key = `${lat.toFixed(5)},${lon.toFixed(5)}`;
    const cached = await getFromDB('addressCache', key);
    if (cached && Date.now() - cached.timestamp < 3600000) return cached.address;

    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`, {
            headers: { "Accept-Language": "km" }
        });
        if (!response.ok) throw new Error('Nominatim API មិនឆ្លើយតប');
        const data = await response.json();
        const address = data.address ? (data.address.road || data.address.village || 'គ្មានឈ្មោះផ្លូវ ឬភូមិ') : 'មិនអាចរកអាសយដ្ឋានបាន';
        await putToDB('addressCache', { key, address, timestamp: Date.now() });
        return address;
    } catch {
        return 'មិនអាចរកអាសយដ្ឋានបាន';
    }
}

let allowedLocationsPromise = null;
async function fetchAllowedLocations() {
    const now = Date.now();
    if (allowedLocationsCache && now - allowedLocationsCacheTime < ALLOWED_LOCATIONS_CACHE_MS) return allowedLocationsCache;
    if (allowedLocationsPromise) return allowedLocationsPromise;

    allowedLocationsPromise = (async () => {
        try {
            const response = await fetchWithRetry('api.php?action=get_data', 3, 1000);
            const result = await response.json();
            if (result.status !== 'success' || !result.data.allowedLocations) throw new Error('Invalid data from API');
            allowedLocationsCache = result.data.allowedLocations;
            allowedLocationsCacheTime = now;
            return allowedLocationsCache;
        } catch (error) {
            showStatus(elements.statusEl, 'មានបញ្ហាក្នុងការទាញទីតាំងអនុញ្ញាត', 'error');
            elements.scanButton.disabled = true;
            return [];
        } finally {
            allowedLocationsPromise = null;
        }
    })();
    return allowedLocationsPromise;
}

const userTimeSettingsCache = {};
const userTimeSettingsCacheTime = {};
const userTimeSettingsFetchPromise = {};

async function fetchUserTimeSettings(userId) {
    const now = Date.now();
    if (userTimeSettingsCache[userId] && userTimeSettingsCacheTime[userId] && now - userTimeSettingsCacheTime[userId] < USER_TIME_SETTINGS_CACHE_MS) {
        return userTimeSettingsCache[userId];
    }
    if (userTimeSettingsFetchPromise[userId]) return userTimeSettingsFetchPromise[userId];

    userTimeSettingsFetchPromise[userId] = (async () => {
        try {
            const response = await fetch('api.php?action=get_data');
            if (!response.ok) throw new Error(`Failed to fetch time settings: ${response.status}`);
            const result = await response.json();
            if (result.status === 'success') {
                const user = result.data.users.find(u => u.id === userId);
                if (user && user.timeSettings) {
                    if (user.timeSettings.check_in_ranges) {
                        user.timeSettings.check_in_ranges.forEach(range => {
                            if (typeof range.start === 'string') range.start = parseTimeToMinutes(range.start);
                            if (typeof range.end === 'string') range.end = parseTimeToMinutes(range.end);
                        });
                    }
                    if (user.timeSettings.check_out_ranges) {
                        user.timeSettings.check_out_ranges.forEach(range => {
                            if (typeof range.start === 'string') range.start = parseTimeToMinutes(range.start);
                            if (typeof range.end === 'string') range.end = parseTimeToMinutes(range.end);
                        });
                    }
                    userTimeSettingsCache[userId] = user.timeSettings;
                    userTimeSettingsCacheTime[userId] = now;
                    return user.timeSettings;
                }
                throw new Error('No time settings found for user');
            }
            throw new Error(result.message || 'Failed to fetch time settings');
        } catch {
            return null;
        } finally {
            delete userTimeSettingsFetchPromise[userId];
        }
    })();
    return userTimeSettingsFetchPromise[userId];
}

function parseTimeToMinutes(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
}

async function getServerTime() {
    try {
        const resp = await fetch('api.php?action=get_server_time');
        if (!resp.ok) throw new Error('Failed to fetch server time');
        const data = await resp.json();
        if (data.status === 'success' && data.server_time) return new Date(data.server_time);
        throw new Error('Invalid server time response');
    } catch {
        return null;
    }
}

function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = x => x * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

async function validateQRCodeLocation({ latitude, longitude }) {
    try {
        const user = await getFromDB('loggedInUser', 'user');
        if (!user?.id) throw new Error('User not logged in');

        const allowedLocations = await fetchAllowedLocations();
        const matchedLocation = allowedLocations.find(loc => {
            const allowedUser = loc.users?.find(u => u.user_id === user.id);
            if (!allowedUser) return false;
            // No tolerance check, just match by user
            return latitude === loc.latitude && longitude === loc.longitude;
        });

        if (!matchedLocation) {
            showStatus(elements.statusEl, 'ទីតាំង QR Code មិនត្រូវគ្នានឹងទីតាំងអនុញ្ញាត!', 'error');
            elements.scanButton.disabled = true;
            elements.branchSelect.disabled = false;
            return;
        }

        let realPosition;
        try {
            realPosition = await getCurrentPosition({ enableHighAccuracy: true, timeout: 5000, maximumAge: 10000 });
        } catch {
            try {
                realPosition = await getCurrentPosition({ enableHighAccuracy: false, timeout: 2000, maximumAge: 60000 });
            } catch {
                showStatus(elements.statusEl, 'សូមអនុញ្ញាតឲ្យប្រើ GPS ដើម្បីបញ្ជាក់ទីតាំង!', 'error');
                elements.scanButton.disabled = true;
                return;
            }
        }
        const realLat = realPosition.coords.latitude;
        const realLon = realPosition.coords.longitude;

        // No tolerance, just check if user is allowed at this location
        scannedLocation = { ...scannedLocation, name: matchedLocation.name, branch: matchedLocation.branch };
        elements.branchSelect.value = matchedLocation.branch;
        elements.userBranchEl.value = matchedLocation.branch;
        elements.branchSelect.disabled = true;
        elements.locationEl.textContent = `ទីតាំង: ${matchedLocation.name} (${matchedLocation.branch})`;
        showStatus(elements.statusEl, `ទីតាំងត្រឹមត្រូវ (${matchedLocation.name} - ${matchedLocation.branch})`, 'success');
        elements.scanButton.disabled = false;
        handleScan();
    } catch (error) {
        showStatus(elements.statusEl, 'មិនអាចផ្ទៀងផ្ទាត់ទីតាំងបាន: ' + error.message, 'error');
        elements.scanButton.disabled = true;
    }
}

function initializeQRScanner() {
    if (!elements.qrScannerContainer) {
        showStatus(elements.statusEl, 'មិនអាចចាប់ផ្តើម QR Scanner បាន!', 'error');
        return;
    }

    while (elements.qrScannerContainer.firstChild) {
        elements.qrScannerContainer.removeChild(elements.qrScannerContainer.firstChild);
    }

    const video = document.createElement('video');
    video.setAttribute('playsinline', 'true');
    video.style.width = '100%';
    video.style.height = 'auto';
    elements.qrScannerContainer.appendChild(video);

    const cameraOverlay = document.createElement('div');
    cameraOverlay.style.position = 'absolute';
    cameraOverlay.style.top = '0';
    cameraOverlay.style.left = '0';
    cameraOverlay.style.width = '100%';
    cameraOverlay.style.height = '100%';
    cameraOverlay.style.display = 'flex';
    cameraOverlay.style.alignItems = 'center';
    cameraOverlay.style.justifyContent = 'center';
    cameraOverlay.style.pointerEvents = 'none';
    cameraOverlay.style.zIndex = '10';
    cameraOverlay.innerHTML = `
        <div style="background:rgba(0,0,0,0.18);border-radius:1.2rem;width:100%;height:100%;position:absolute;top:0;left:0;"></div>
    `;
    elements.qrScannerContainer.appendChild(cameraOverlay);

    function startScanner() {
        const constraintsList = [
            { video: { facingMode: { ideal: 'environment' }, width: { ideal: 320 }, height: { ideal: 320 } } },
            { video: { facingMode: { ideal: 'environment' }, width: { ideal: 480 }, height: { ideal: 480 } } },
            { video: { facingMode: { ideal: 'environment' } } }
        ];

        let tried = 0;
        function tryNextConstraint() {
            if (tried >= constraintsList.length) {
                showStatus(elements.statusEl, 'មិនអាចបើកកាមេរ៉ា!', 'error');
                cameraOverlay.innerHTML = `<span style="color:#e74c3c;font-size:1.1rem;font-weight:600;background:rgba(255,255,255,0.9);border-radius:0.7rem;padding:0.18rem 0.6rem;">មិនអាចបើកកាមេរ៉ា</span>`;
                return;
            }
            navigator.mediaDevices.getUserMedia(constraintsList[tried])
                .then(stream => {
                    video.srcObject = stream;
                    video.play();
                    qrScanner = setInterval(() => scanQRCode(video), 100);
                    showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
                    video.onplaying = () => {
                        cameraOverlay.style.display = 'none';
                        addFlashlightToggle(video);
                    };
                })
                .catch(() => {
                    tried++;
                    tryNextConstraint();
                });
        }
        tryNextConstraint();
    }

    if (!window.jsQR) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        script.onload = startScanner;
        script.onerror = () => showStatus(elements.statusEl, 'មិនអាចផ្ទុក QR Scanner បាន!', 'error');
        document.head.appendChild(script);
    } else {
        startScanner();
    }
}

function addFlashlightToggle(video) {
    let existing = document.getElementById('flashlightToggleBtn');
    if (existing) existing.remove();

    const stream = video.srcObject;
    if (!stream) return;
    const track = stream.getVideoTracks()[0];
    if (!track) return;

    const capabilities = track.getCapabilities && track.getCapabilities();
    if (!capabilities || !capabilities.torch) return;

    const btn = document.createElement('button');
    btn.id = 'flashlightToggleBtn';
    btn.type = 'button';
    btn.style.position = 'absolute';
    btn.style.bottom = '18px';
    btn.style.right = '18px';
    btn.style.zIndex = '30';
    btn.style.background = 'rgba(255,255,255,0.92)';
    btn.style.border = 'none';
    btn.style.borderRadius = '50%';
    btn.style.width = '48px';
    btn.style.height = '48px';
    btn.style.display = 'flex';
    btn.style.alignItems = 'center';
    btn.style.justifyContent = 'center';
    btn.style.boxShadow = '0 2px 8px #0002';
    btn.style.fontSize = '2rem';
    btn.style.cursor = 'pointer';
    btn.style.transition = 'background 0.2s';
    btn.innerHTML = '<img src="https://cdn-icons-png.flaticon.com/512/6422/6422016.png" alt="flashlight" style="width:28px;height:28px;">';

    let torchOn = false;
    btn.onclick = async () => {
        try {
            torchOn = !torchOn;
            await track.applyConstraints({ advanced: [{ torch: torchOn }] });
            btn.style.background = torchOn ? '#ffe066' : 'rgba(255,255,255,0.92)';
            btn.innerHTML = torchOn
                ? '<img src="https://cdn-icons-png.flaticon.com/512/6422/6422016.png" alt="flashlight" style="width:28px;height:28px;filter:drop-shadow(0 0 8px #ffc107);">' 
                : '<img src="https://cdn-icons-png.flaticon.com/512/6422/6422016.png" alt="flashlight" style="width:28px;height:28px;">';
        } catch {
            btn.disabled = true;
            btn.title = 'មិនគាំទ្រពិល';
        }
    };
    if (elements.qrScannerContainer) elements.qrScannerContainer.appendChild(btn);
}

(function addCheckInOutBtn() {
    if (document.getElementById('quickCheckBtn')) return;
    const btn = document.createElement('button');
    btn.id = 'quickCheckBtn';
    btn.className = 'btn btn-primary mb-3';
    btn.style.width = '100%';
    btn.innerHTML = '<i class="fa-solid fa-qrcode"></i> ចាប់ផ្តើមស្កេន Check-In/Out';

    let cameraOverlay = document.getElementById('fullscreenCameraOverlay');
    if (!cameraOverlay) {
        cameraOverlay = document.createElement('div');
        cameraOverlay.id = 'fullscreenCameraOverlay';
        cameraOverlay.style.position = 'fixed';
        cameraOverlay.style.top = '0';
        cameraOverlay.style.left = '0';
        cameraOverlay.style.width = '100vw';
        cameraOverlay.style.height = '100vh';
        cameraOverlay.style.background = 'rgba(0,0,0,0.97)';
        cameraOverlay.style.zIndex = '3000';
        cameraOverlay.style.display = 'none';
        cameraOverlay.style.justifyContent = 'center';
        cameraOverlay.style.alignItems = 'center';
        cameraOverlay.style.flexDirection = 'column';
        cameraOverlay.innerHTML = `
            <div style="position:relative;width:90vw;max-width:400px;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;">
                <div id="fullscreenQRScannerContainer" style="width:100%;height:100%;"></div>
                <button id="closeFullscreenCamera" style="position:absolute;top:10px;right:10px;z-index:10;background:#fff;border:none;border-radius:50%;width:38px;height:38px;font-size:1.5rem;box-shadow:0 2px 8px #0002;cursor:pointer;">×</button>
            </div>
        `;
        document.body.appendChild(cameraOverlay);
    }

    const scanSection = document.getElementById('scanSection');
    const qrContainer = document.getElementById('qrScannerContainer');
    if (scanSection && qrContainer) {
        qrContainer.style.display = 'none';
        qrContainer.parentNode.insertBefore(btn, qrContainer);

        btn.addEventListener('click', () => {
            const overlayQR = document.getElementById('fullscreenQRScannerContainer');
            if (overlayQR && qrContainer) {
                qrContainer.style.display = '';
                overlayQR.appendChild(qrContainer);
            }
            cameraOverlay.style.display = 'flex';
            initializeQRScanner();
            btn.style.display = 'none';
        });

        cameraOverlay.querySelector('#closeFullscreenCamera').onclick = autoStopCamera;

        const originalShowStatus = showStatus;
        window.showStatus = function(element, message, type = 'default', action = null) {
            originalShowStatus(element, message, type, action);
            if (typeof message === 'string' && (message.includes('ទីតាំងត្រឹមត្រូវ') || message.includes('អ្នកមិនស្ថិតនៅក្នុងតំបនដែនអាចស្កេនបាន!')) && cameraOverlay.style.display !== 'none') {
                autoStopCamera();
            }
        };
    }
})();



function showCameraUIAfterScan() {
    if (elements.qrScannerContainer.querySelector('.camera-ui-after-scan')) {
        elements.qrScannerContainer.removeChild(elements.qrScannerContainer.querySelector('.camera-ui-after-scan'));
    }
    const doneOverlay = document.createElement('div');
    doneOverlay.className = 'camera-ui-after-scan';
    doneOverlay.style.position = 'absolute';
    doneOverlay.style.top = '0';
    doneOverlay.style.left = '0';
    doneOverlay.style.width = '100%';
    doneOverlay.style.height = '100%';
    doneOverlay.style.background = 'rgba(44, 203, 112, 0.18)';
    doneOverlay.style.display = 'flex';
    doneOverlay.style.alignItems = 'center';
    doneOverlay.style.justifyContent = 'center';
    doneOverlay.style.zIndex = '20';
    doneOverlay.innerHTML = `
        <div style="background:rgba(255,255,255,0.95);border-radius:1.2rem;padding:1.2rem 1.5rem;box-shadow:0 2px 8px rgba(44,62,80,0.07);display:flex;flex-direction:column;align-items:center;">
            <i class="fa-solid fa-circle-check" style="color:#27ae60;font-size:2.5rem;margin-bottom:0.5rem;"></i>
            <span style="color:#27ae60;font-size:1.1rem;font-weight:600;">ស្កេនរួចរាល់</span>
        </div>
    `;
    elements.qrScannerContainer.appendChild(doneOverlay);
    setTimeout(() => {
        if (doneOverlay.parentNode) doneOverlay.parentNode.removeChild(doneOverlay);
        autoStopCamera(); // Close camera after showing "ស្កេនរួចរាល់"
    }, 1200);
}

function scanQRCode(video) {
    if (!video || video.readyState < 2) return;
    if (!qrScanCanvas) qrScanCanvas = document.createElement('canvas');
    qrScanCanvas.width = video.videoWidth;
    qrScanCanvas.height = video.videoHeight;
    const ctx = qrScanCanvas.getContext('2d');
    ctx.drawImage(video, 0, 0, qrScanCanvas.width, qrScanCanvas.height);
    const imageData = ctx.getImageData(0, 0, qrScanCanvas.width, qrScanCanvas.height);
    if (!window.jsQR) return;
    const code = jsQR(imageData.data, imageData.width, imageData.height);
    if (code && code.data) {
        const now = Date.now();
        if (code.data === lastScannedQR && now - lastScannedAt < QR_DUPLICATE_INTERVAL) return;
        lastScannedQR = code.data;
        lastScannedAt = now;
        try {
            if (code.data.startsWith('geo:')) {
                const [latitude, longitude] = code.data.replace('geo:', '').split(',').map(parseFloat);
                if (isNaN(latitude) || isNaN(longitude)) throw new Error('Invalid latitude or longitude');
                scannedLocation = { latitude, longitude };
                locationReady = true;
                validateQRCodeLocation({ latitude, longitude });
                showCameraUIAfterScan();
            } else {
                throw new Error('Invalid QR code format');
            }
        } catch (error) {
            showStatus(elements.statusEl, 'QR Code មិនត្រឹមត្រូវ!', 'error');
            locationReady = false;
            elements.scanButton.disabled = true;
        }
    }
}

function stopQRScanner() {
    if (qrScanner) {
        clearInterval(qrScanner);
        qrScanner = null;
    }
    if (elements.qrScannerContainer && elements.qrScannerContainer.firstChild) {
        const video = elements.qrScannerContainer.firstChild;
        const stream = video.srcObject;
        if (stream) stream.getTracks().forEach(track => track.stop());
        elements.qrScannerContainer.removeChild(video);
    }
}



async function handleScan() {
    if (isProcessing) return;
    isProcessing = true;
    elements.scanButton.disabled = true;
    elements.scanSpinner.style.display = 'inline-block';
    elements.scanButton.style.display = 'none';

    let spinnerTimeout = setTimeout(() => {
        elements.scanSpinner.style.display = 'none';
        elements.scanButton.style.display = 'block';
        elements.scanButton.disabled = !locationReady;
    }, 1200);

    try {
        // Try to get server time with retries and longer timeout, fallback to device time if failed
        let serverTime = null;
        for (let i = 0; i < 3; i++) {
            serverTime = await Promise.race([
                getServerTime(),
                new Promise(resolve => setTimeout(() => resolve(null), 3500))
            ]);
            if (serverTime) break;
        }
        if (!serverTime) {
            // Fallback: use device time and show a warning, but allow scan to proceed
            showStatus(elements.statusEl, 'មិនអាចផ្ទៀងផ្ទាត់ម៉ោងតាមម៉ាស៊ីនមេបាន - ប្រើម៉ោងក្នុងទូរស័ព្ទ', 'error');
            serverTime = new Date();
        }
        const deviceTime = new Date();
        const diffMinutes = Math.abs(deviceTime.getTime() - serverTime.getTime()) / 60000;
        if (diffMinutes > 2) {
            showStatus(elements.statusEl, `ម៉ោងក្នុងទូរស័ព្ទ (${deviceTime.toLocaleTimeString()}) ខុសពីម៉ោងតំបន់ (${serverTime.toLocaleTimeString()}) ។ សូមកែម៉ោងឲ្យត្រឹមត្រូវ!`, 'error');
            return;
        }

        const user = await getFromDB('loggedInUser', 'user');
        if (!user?.token || !await checkToken(user.token)) {
            showStatus(elements.statusEl, 'Token មិនត្រឹមត្រូវ សូមចូលឡើងវិញ!', 'error');
            logout();
            return;
        }

        if (!locationReady || !scannedLocation) {
            showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!', 'error');
            return;
        }

        const allowedLocations = await fetchAllowedLocations();
        if (!allowedLocations.length) {
            showStatus(elements.statusEl, 'មិនទាន់មានទីតាំងអនុញ្ញាត មិនអាចស្កេនបាន', 'error');
            return;
        }

        const matchedLocation = allowedLocations.find(loc => {
            const allowedUser = loc.users.find(u => u.user_id === user.id);
            if (!allowedUser) return false;
            const tolerance = parseFloat(allowedUser.tolerance);
            return Math.abs(scannedLocation.latitude - loc.latitude) <= tolerance && Math.abs(scannedLocation.longitude - loc.longitude) <= tolerance;
        });

        if (!matchedLocation) {
            showStatus(elements.statusEl, 'ទីតាំង QR Code មិនត្រូវគ្នានឹងទីតាំងអនុញ្ញាត!', 'error');
            return;
        }

        const validationError = validateFormData({
            userName: elements.userName.value,
            action: elements.actionSelect.value,
            userId: elements.userIdEl.value,
            branch: elements.branchSelect.value,
            folder: elements.userFolderEl.value,
            workplace: elements.userWorkplaceEl.value,
            latitude: scannedLocation.latitude,
            longitude: scannedLocation.longitude
        });
        if (validationError) {
            showStatus(elements.statusEl, validationError, 'error');
            return;
        }

        const now = new Date();
        const date = now.toLocaleDateString("km-KH", { day: "2-digit", month: "2-digit", year: "numeric" });
        const time = now.toLocaleTimeString("km-KH", { hour12: true, hour: "2-digit", minute: "2-digit", second: "2-digit" });
        const location = `Lat: ${scannedLocation.latitude}, Long: ${scannedLocation.longitude}`;
        const addressPromise = getAddress(scannedLocation.latitude, scannedLocation.longitude);

        let scanStatus = 'Good';
        let lateMinutes = 0;
        const timeSettings = await Promise.race([
            fetchUserTimeSettings(user.id),
            new Promise(resolve => setTimeout(() => resolve(null), 1200))
        ]);
        if (timeSettings) {
            const ranges = elements.actionSelect.value === 'Check-In' ? timeSettings.check_in_ranges : timeSettings.check_out_ranges;
            if (ranges?.length) {
                const { status, lateMinutes: calculatedLateMinutes } = calculateScanStatus(now, ranges);
                scanStatus = status;
                lateMinutes = calculatedLateMinutes;
                if (elements.actionSelect.value === 'Check-Out' && !scanStatus.includes('Good')) {
                    showEarlyScanPopup();
                    return;
                }
            }
        }

        const address = await Promise.race([
            addressPromise,
            new Promise(resolve => setTimeout(() => resolve('កំពុងទាញអាសយដ្ឋាន...'), 1500))
        ]);

        const formData = new FormData();
        formData.append("ឈ្មោះ", elements.userName.value);
        formData.append("ID", elements.userIdEl.value);
        formData.append("ប្រភេទស្កេន", elements.actionSelect.value);
        formData.append("ថ្ងៃ", date);
        formData.append("ម៉ោង", time);
        formData.append("location", location);
        formData.append("address", address);
        formData.append("សាខា", elements.branchSelect.value);
        formData.append("folder", elements.userFolderEl.value);
        formData.append("Department", elements.userDepartmentEl.value);
        formData.append("Position", elements.userPositionEl.value);
        formData.append("workplace", elements.userWorkplaceEl.value);
        formData.append("token", user.token);
        formData.append("location_name", matchedLocation.name);
        formData.append("status", scanStatus || '');
        formData.append("early_reason", elements.earlyReason?.value?.trim() || '');

        await submitScan(formData, date, time, location, address, scanStatus, lateMinutes);
    } catch (error) {
        showStatus(elements.statusEl, 'កំហុសក្នុងការស្កេន: ' + error.message, 'error');
    } finally {
        clearTimeout(spinnerTimeout);
        isProcessing = false;
        elements.scanSpinner.style.display = 'none';
        elements.scanButton.style.display = 'block';
        elements.scanButton.disabled = !locationReady;
        autoStopCamera();
    }
}

function calculateScanStatus(scanTime, ranges, isEarlyWithReason = false) {
    if (isEarlyWithReason) return { status: 'Good', lateMinutes: 0 };
    const scanMinutes = scanTime.getHours() * 60 + scanTime.getMinutes();
    for (const range of ranges) {
        if (scanMinutes >= range.start && scanMinutes <= range.end) {
            return { status: range.status, lateMinutes: 0 };
        }
    }
    let minLate = null;
    let status = '🔴 Late';
    for (const range of ranges) {
        if (range.status !== 'Good') continue;
        if (scanMinutes < range.start) {
            const late = range.start - scanMinutes;
            if (minLate === null || late < minLate) minLate = late;
        } else if (scanMinutes > range.end) {
            const late = scanMinutes - range.end;
            if (minLate === null || late < minLate) minLate = late;
        }
    }
    return { status, lateMinutes: minLate || 0 };
}

async function submitScan(formData, date, time, location, address, scanStatus, lateMinutes = 0) {
    const userId = elements.userIdEl.value;
    const scanType = elements.actionSelect.value;
    if (userId && scanType) setLastScanTypeToStorage(userId, scanType);

    elements.loadingPopup.classList.add('show');
    let telegramPromise;
    try {
        const result = await saveToBackend(formData);
        if (result.success) {
            lastScanType = scanType;
            await putToDB('lastScanType', { key: 'scanType', value: lastScanType });
            elements.actionEl.textContent = scanType;
            elements.timestampEl.textContent = `${date} ${time}`;
            const mapUrl = `https://www.google.com/maps?q=${scannedLocation.latitude},${scannedLocation.longitude}`;
            elements.locationEl.innerHTML = `${location}\n${address} (<a href="${mapUrl}" target="_blank" rel="noopener noreferrer" style="color: #3498db; text-decoration: underline;">មើលទីតាំង</a>)`;

            const formattedStatus = scanStatus.includes('🔵') || scanStatus.includes('🔴') ? scanStatus : (scanStatus === 'Good' ? '🔵 Good' : '🔴 Late');
            const earlyReasonText = formData.get("early_reason") ? `\nមូលហេតុចេញមុន: ${formData.get("early_reason")}` : '';
            const lateText = lateMinutes > 0 ? `\nចំនួននាទីយឺត: ${lateMinutes} នាទី` : '';
            const telegramMessage = `Name: ${elements.userName.value}\nStatus: ${scanType} (${formattedStatus})${earlyReasonText}${lateText}\n\nID: ${userId}\nDepartment: ${elements.userDepartmentEl.value}\nPosition: ${elements.userPositionEl.value}\nWorkplace: ${elements.userWorkplaceEl.value}\nDate/Time: ${date} ${time}\nArea: ${elements.branchSelect.value}\nLocation: ${address}\n\n[📍 មើលទីតាំង](https://www.google.com/maps?q=${scannedLocation.latitude},${scannedLocation.longitude})`;

            telegramPromise = sendToTelegram(telegramMessage).catch(error => {
                console.error('Failed to send to Telegram:', error);
                showStatus(elements.statusEl, `បានរក្សាទុកទិន្នន័យ ប៉ុន្តែមិនអាចផ្ញើទៅ Telegram: ${error.message}`, 'error');
            });

            showStatus(elements.statusEl, `បានបញ្ជូនរួចរាល់ - ${elements.userName.value}`, 'success');
            showPopup(`បានបញ្ជូនរួចរាល់ - ${elements.userName.value}`, 'success');

            const nextAction = scanType === 'Check-In' ? 'Check-Out' : 'Check-In';
            elements.actionSelect.value = nextAction;
        } else {
            await saveToIndexedDB(formData);
            console.error("Failed to save data, stored in IndexedDB:", { formData: Object.fromEntries(formData), error: result.error });
            showStatus(elements.statusEl, `មានបញ្ហាក្នុងការបញ្ជូនទិន្នន័យ ប៉ុន្តែបានរក្សាទុកជាបណ្តោះអាសន្ន: ${result.error.message}`, 'error');
            showPopup('បញ្ជូនបរាជ័យ បានរក្សាទុកជាបណ្តោះអាសន្ន!', 'error');
            await retryQueuedScans();
        }
    } catch (error) {
        await saveToIndexedDB(formData);
        showStatus(elements.statusEl, `កំហុសបណ្តាញ: ${error.message}`, 'error');
        showPopup('បញ្ជូនបរាជ័យ!', 'error');
    } finally {
        elements.loadingPopup.classList.remove('show');
        showStatus(elements.statusEl, `សូមបញ្ជាក់ ${elements.actionSelect.value} បន្ទាប់`);
        if (telegramPromise) await telegramPromise;
    }
}

function showStatus(element, message, type = 'default', action = null) {
    element.textContent = '';
    element.classList.remove('show');
    element.style.transition = 'background 0.18s, color 0.18s, border-color 0.18s';
    let color, bg, border;
    switch (type) {
        case 'success':
            color = '#27ae60';
            bg = 'rgba(231,255,231,0.97)';
            border = '#27ae60';
            break;
        case 'error':
            color = '#e74c3c';
            bg = 'rgba(255,245,245,0.97)';
            border = '#e74c3c';
            break;
        case 'warning':
            color = '#f39c12';
            bg = 'rgba(255,250,230,0.97)';
            border = '#f39c12';
            break;
        default:
            color = '#2980b9';
            bg = 'rgba(240,248,255,0.97)';
            border = '#4A90E2';
    }
    element.style.color = color;
    element.style.background = bg;
    element.style.borderColor = border;
    element.style.borderWidth = '2px';
    element.style.borderStyle = 'solid';
    element.style.borderRadius = '0.7rem';
    element.style.padding = '0.7rem 1.1rem';
    element.style.marginBottom = '0.7rem';
    element.style.fontWeight = '600';
    element.style.fontSize = '1.08rem';
    element.style.boxShadow = '0 2px 8px rgba(44,62,80,0.07)';
    element.style.display = 'block';

    // Remove old buttons
    Array.from(element.querySelectorAll('button')).forEach(btn => btn.remove());

    // Add action button if needed
    if (action) {
        const actionBtn = document.createElement('button');
        actionBtn.textContent = action.text;
        actionBtn.className = 'btn btn-sm btn-secondary mt-2';
        actionBtn.onclick = action.callback;
        element.appendChild(actionBtn);
    }

    // Add icon
    let icon = '';
    if (type === 'success') icon = '<i class="fa-solid fa-circle-check" style="color:#27ae60;margin-right:0.5em;"></i>';
    else if (type === 'error') icon = '<i class="fa-solid fa-circle-xmark" style="color:#e74c3c;margin-right:0.5em;"></i>';
    else if (type === 'warning') icon = '<i class="fa-solid fa-triangle-exclamation" style="color:#f39c12;margin-right:0.5em;"></i>';
    else icon = '<i class="fa-solid fa-info-circle" style="color:#2980b9;margin-right:0.5em;"></i>';

    element.innerHTML = icon + message;
    if (action) element.appendChild(actionBtn);

    element.classList.add('show');
    element.style.opacity = '1';
}


function showPopup(message, type = 'success') {
    elements.popupMessage.textContent = message;
    elements.scanPopup.className = 'popup ' + type;
    elements.scanPopup.style.display = 'block';

    const stayBtn = document.createElement('button');
    stayBtn.innerHTML = '<span style="display:inline-block;animation:iconBounce 1s infinite alternate;">🙏</span>';
    stayBtn.className = 'btn btn-secondary mt-2';
    elements.scanPopup.appendChild(stayBtn);

    let audioUrl = elements.actionSelect.value === 'Check-In' ? 'CheckIn.mp3' : 'CheckOut.mp3';
    const audio = new Audio(audioUrl);
    audio.play().catch(err => console.log('Audio error:', err));

    const popupTimeout = setTimeout(() => {
        elements.scanPopup.style.opacity = '0';
        setTimeout(() => {
            elements.scanPopup.style.display = 'none';
            elements.scanPopup.style.opacity = '1';
            elements.scanPopup.removeChild(stayBtn);
        }, 200);
    }, 3000);

    const redirectTimeout = setTimeout(() => {
        window.location.href = `/worker/logs.php?username=${encodeURIComponent(elements.userName.value)}&id=${encodeURIComponent(elements.userIdEl.value)}&branch=${encodeURIComponent(elements.branchSelect.value)}&folder=${encodeURIComponent(elements.userFolderEl.value)}`;
    }, 60000);

    stayBtn.onclick = () => {
        clearTimeout(popupTimeout);
        clearTimeout(redirectTimeout);
        elements.scanPopup.style.display = 'none';
        elements.scanPopup.removeChild(stayBtn);
        showStatus(elements.statusEl, 'អ្នកនៅតែស្ថិតក្នុងទំព័រនេះ');
        elements.scanButton.style.display = 'block';
    };
}

function showEarlyScanPopup() {
    elements.earlyScanPopup.style.display = 'flex';
    elements.earlyReason.value = '';
    setTimeout(() => elements.earlyReason.focus(), 10);
}

elements.earlyScanPopup.addEventListener('transitionend', () => {
    if (elements.earlyScanPopup.style.display === 'flex') {
        elements.earlyReason.focus();
    }
});

elements.earlyReason.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        elements.submitEarlyScan.click();
    } else if (e.key === 'Escape') {
        elements.cancelEarlyScan.click();
    }
});

elements.submitEarlyScan.addEventListener('click', async () => {
    const reason = elements.earlyReason.value.trim();
    if (!reason) {
        showPopup('សូមបញ្ចូលមូលហេតុ!', 'error');
        return;
    }

    const now = new Date();
    const date = now.toLocaleDateString("km-KH", { day: "2-digit", month: "2-digit", year: "numeric" });
    const time = now.toLocaleTimeString("km-KH", { hour12: true, hour: "2-digit", minute: "2-digit", second: "2-digit" });
    const location = `Lat: ${scannedLocation.latitude}, Long: ${scannedLocation.longitude}`;
    const address = await getAddress(scannedLocation.latitude, scannedLocation.longitude);

    const formData = new FormData();
    formData.append("ឈ្មោះ", elements.userName.value);
    formData.append("ID", elements.userIdEl.value);
    formData.append("ប្រភេទស្កេន", elements.actionSelect.value);
    formData.append("ថ្ងៃ", date);
    formData.append("ម៉ោង", time);
    formData.append("location", location);
    formData.append("address", address);
    formData.append("សាខា", elements.branchSelect.value);
    formData.append("folder", elements.userFolderEl.value);
    formData.append("Department", elements.userDepartmentEl.value);
    formData.append("Position", elements.userPositionEl.value);
    formData.append("workplace", elements.userWorkplaceEl.value);
    formData.append("token", (await getFromDB('loggedInUser', 'user')).token);
    formData.append("status", "Good");
    formData.append("early_reason", reason);
    formData.append("location_name", scannedLocation.name);
    await submitScan(formData, date, time, location, address, 'Good', 0);
    elements.earlyScanPopup.style.display = 'none';
    autoStopCamera();
});

elements.cancelEarlyScan.addEventListener('click', () => {
    elements.earlyScanPopup.style.display = 'none';
    elements.scanButton.style.display = 'block';
    elements.scanButton.disabled = !locationReady;
    elements.scanSpinner.style.display = 'none';
    showStatus(elements.statusEl, `បានជ្រើស ${elements.actionSelect.value}`);
    autoStopCamera();
});

async function sendToTelegram(message) {
    const user = await getFromDB('loggedInUser', 'user');
    try {
        const response = await fetch('api.php?action=send_telegram', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message,
                department: elements.userDepartmentEl.value || 'specialist',
                token: user.token,
                parse_mode: 'Markdown'
            })
        });
        const result = await response.json();
        if (result.status !== 'success') throw new Error(result.message || 'Telegram send failed');
        console.log('Telegram message sent successfully');
        return true;
    } catch (error) {
        console.error('Telegram send error:', error.message);
        throw error;
    }
}

async function getCurrentPosition(options = { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) reject(new Error('មិនគាំទ្រការទទួល GPS!'));
        else navigator.geolocation.getCurrentPosition(resolve, reject, options);
    });
}

async function fetchWithRetry(url, retries = 3, delay = 1000) {
    for (let i = 0; i < retries; i++) {
        try {
            const response = await fetch(url);
            if (response.ok) return response;
            console.warn(`Fetch attempt ${i + 1} failed: ${response.status}`);
        } catch (e) {
            console.error(`Fetch attempt ${i + 1} error:`, e);
        }
        if (i < retries - 1) await new Promise(resolve => setTimeout(resolve, delay));
    }
    throw new Error('Failed to fetch after retries');
}

function checkForUpdates() {
    fetch(`/version.json?v=${Date.now()}`)
        .then(response => response.json())
        .then(data => {
            if (data.version !== APP_VERSION) {
                navigator.serviceWorker.getRegistration().then(registration => {
                    registration.update().then(() => showUpdateNotification(registration));
                });
            }
        })
        .catch(error => console.error('Update check failed:', error));
}

async function showUpdateNotification(registration) {
    await retryQueuedScans();
    showPopup('មានកំណែថ្មី! កំពុងធ្វើបច្ចុប្បន្នភាព...', 'success');
    setTimeout(() => {
        registration.waiting?.postMessage({ type: 'CHECK_UPDATE' });
        window.location.reload();
    }, 3000);
}

async function restoreStateAfterUpdate() {
    const lastState = await getFromDB('lastState', 'state');
    if (lastState) {
        await Promise.all([
            putToDB('loggedInUser', lastState.user),
            putToDB('lastScanType', { key: 'scanType', value: lastState.lastScanType }),
            ...(lastState.queuedScans?.map(scan => putToDB('scanQueue', scan)) || []),
            deleteFromDB('lastState', 'state')
        ]);
        lastScanType = lastState.lastScanType;
        locationReady = lastState.locationReady;
        showScanSection(lastState.user);
        retryQueuedScans();
    }
}

function sanitizeInput(input) {
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

setInterval(async () => {
    try {
        const now = Date.now();
        const scans = await getFromDB('scanQueue');
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        for (const scan of scans) {
            if (scan.id && now - scan.id > sevenDays) await deleteFromDB('scanQueue', scan.id);
        }

        const addressCache = await getFromDB('addressCache');
        const twoDays = 2 * 24 * 60 * 60 * 1000;
        for (const entry of addressCache) {
            if (entry.timestamp && now - entry.timestamp > twoDays) await deleteFromDB('addressCache', entry.key);
        }

        const lastState = await getFromDB('lastState', 'state');
        if (lastState && lastState.user && lastState.user.token && lastState.user.id && lastState.timestamp && now - lastState.timestamp > sevenDays) {
            await deleteFromDB('lastState', 'state');
        }
    } catch {}
}, 10000);

/**
 * Speed up PWA startup: show scan section instantly if user is logged in,
 * even before all async data is loaded.
 * Also hide the splash screen/logo ASAP on Android.
 */
(function fastPWAStartup() {
    if (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true
    ) {
        document.addEventListener('DOMContentLoaded', async () => {
            // Hide Android splash screen ASAP (if supported)
            if (navigator.splashscreen && navigator.splashscreen.hide) {
                try { navigator.splashscreen.hide(); } catch {}
            }
            // For Chrome PWA, try to force paint ASAP
            document.body.style.display = '';
            try {
                await openDB();
                const user = await getFromDB('loggedInUser', 'user');
                if (user && user.token) {
                    elements.loginSection.style.display = 'none';
                    elements.scanSection.style.display = 'block';
                    // Fill minimal user info instantly
                    elements.userName.value = user.username || '';
                    elements.userIdEl.value = user.id || '';
                    elements.userDepartmentEl.value = user.department || '';
                    elements.userPositionEl.value = user.position || '';
                    elements.userFolderEl.value = user.folder || '';
                    elements.userWorkplaceEl.value = user.workplace || '';
                    // Don't wait for all async data to finish
                    setTimeout(() => {
                        showStatus(elements.statusEl, 'សូមស្កេន QR Code ទីតាំង!');
                        elements.scanButton.disabled = true;
                    }, 0);
                }
            } catch {}
        });
        // Extra: force repaint to hide splash on some Android PWAs
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 10);
        });
    }
})();

    </script>
</body>

</html>