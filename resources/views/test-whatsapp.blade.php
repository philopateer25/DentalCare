<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test WhatsApp API</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white p-8 rounded-lg shadow-lg max-w-md w-full">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Test WhatsApp Integration</h1>

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-3 rounded mb-6">
        <strong>Important:</strong> UltraMsg needs to be able to download the PDF from your app's URL. If your <code>APP_URL</code> is currently <code>http://localhost:8000</code>, UltraMsg's servers won't be able to reach it. You will receive the text message, but the PDF might fail unless you use a tunneling tool like <strong>ngrok</strong> (e.g., <code>ngrok http 8000</code>) and set that as your APP_URL.
    </div>

    <form action="/test-whatsapp" method="POST">
        @csrf
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2" for="phone">
                Phone Number (with country code, e.g. +2010...)
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="phone" name="phone" type="text" placeholder="+1234567890" required>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-bold mb-2" for="message">
                Message Content
            </label>
            <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="message" name="message" rows="3" required>This is a test message from Laravel!</textarea>
        </div>

        <div class="flex items-center justify-between">
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full" type="submit">
                Send Text & Fake PDF Invoice
            </button>
        </div>
    </form>
</div>

</body>
</html>
