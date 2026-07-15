<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Management System</title>
    <style>
        /* Basic styles for header, body, and footer */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        header {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header .nav-links a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
        }
        header .nav-links a:hover {
            text-decoration: underline;
        }
        .dynamic-content {
            text-align: center;
            margin: 50px;
        }
        footer {
            background-color: #4CAF50;
            color: white;
            text-align: center;
            padding: 10px;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        img {
            max-width: 100%;
            height: auto;
        }
    </style>
    <script>
        // JavaScript to handle dynamic content
        const messages = [
            "Welcome to the Visit Management System!",
            "Easily manage your appointments.",
            "Track visitors effortlessly."
        ];
        const images = [
            "nuvoco-ori.png", // Replace with actual image paths
            "logo.png",
            "image3.jpg"
        ];
        let currentIndex = 0;

        function changeContent() {
            const messageElement = document.getElementById('dynamic-message');
            const imageElement = document.getElementById('dynamic-image');

            currentIndex = (currentIndex + 1) % messages.length;

            messageElement.innerText = messages[currentIndex];
            imageElement.src = images[currentIndex];
        }

        setInterval(changeContent, 3000); // Change content every 3 seconds
    </script>
</head>
<body>
    <!-- Header Section -->
    <header>
        <h1>Visit Management System</h1>
        <div class="nav-links">
            <a href="index.php">Login</a>
            <a href="signup.php">Sign Up</a>
            <a href="#about">About Us</a>
        </div>
    </header>

    <!-- Body Section -->
    <div class="dynamic-content">
        <h2 id="dynamic-message">Welcome to the Visit Management System!</h2>
        <img id="dynamic-image" src="image1.jpg" alt="Dynamic Image">
    </div>

    <!-- Footer Section -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Visit Management System. All rights reserved.</p>
    </footer>
</body>
</html>
