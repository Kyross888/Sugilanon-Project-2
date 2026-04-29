<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- PWA Manifest & Theme -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Luna's POS">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon-96x96.png">
    <link rel="icon" href="favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Analytics</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* CSS Variables: Centralizes color themes so changing a color updates the whole site instantly */
        
         :root {
            --primary: #5a67d8;
            --primary-hover: #434190;
            --bg: #f4f6f9;
            --white: #ffffff;
            --text-dark: #2d3748;
            --text-light: #718096;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #e53e3e;
            --border: #e2e8f0;
        }
        /* CSS Reset: Removes default browser margins/padding for consistent styling across browsers */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        /* Main Body Layout: Uses Flexbox to place the sidebar and main content side-by-side */
        
        body {
            background: var(--bg);
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            /* Prevents the whole page from scrolling; only the content area will scroll */
            color: var(--text-dark);
        }
        /* --- SIDEBAR STYLES --- */
        
        .sidebar {
            width: 250px;
            background: var(--white);
            display: flex;
            flex-direction: column;
            padding: 20px;
            border-right: 1px solid var(--border);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            /* Smooth animation when collapsing */
            z-index: 100;
            flex-shrink: 0;
            /* Prevents the sidebar from squishing when screen size reduces */
        }
        /* Applied via JS to shrink the sidebar */
        
        .sidebar.collapsed {
            width: 80px;
        }
        /* Brand/Logo Area */
        
      .brand {
    margin-bottom: 30px;
    cursor: pointer;
    text-align: center;
}
.brand img {
    max-width: 100%;
    border-radius: 8px;
    transition: 0.3s;
}
.sidebar.collapsed .brand img {
    width: 40px;
}
        /* Navigation Links */
        
        .nav-links {
            list-style: none;
        }
        
        .nav-links li {
            margin-bottom: 10px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-light);
            padding: 12px 15px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: 0.2s;
            white-space: nowrap;
            /* Prevents text from wrapping when sidebar collapses */
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-links i {
            min-width: 30px;
            font-size: 18px;
        }
        /* Hides the text labels when the sidebar is collapsed */
        
        .sidebar.collapsed .nav-links span {
            display: none;
        }
        /* --- MAIN CONTENT AREA --- */
        
        .main {
            flex-grow: 1;
            /* Takes up all remaining space next to the sidebar */
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }
        /* Top Navigation Header */
        
        .header {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            height: 70px;
        }
        /* The scrollable area containing the dashboard widgets */
        
        .content {
            padding: 25px;
            overflow-y: auto;
            flex-grow: 1;
        }
        /* --- DASHBOARD WIDGETS --- */
        /* Stats Cards: Uses CSS Grid to automatically flow cards onto the next row on smaller screens */
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            border: 1px solid var(--border);
        }
        
        .stat-card small {
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
        }
        
        .stat-card h2 {
            margin: 5px 0;
            font-size: 24px;
        }
        
        .trend {
            font-size: 12px;
            font-weight: bold;
        }
        /* Charts: Grid layout prioritizing the main line chart (1.8fr) over the doughnut chart (1.2fr) */
        
        .chart-grid {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .chart-box {
            background: white;
            height: 350px;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .chart-box h4 {
            margin-bottom: 15px;
            color: var(--text-light);
            font-size: 14px;
            display: flex;
            justify-content: space-between;
        }
        /* Table Area */
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            overflow-x: auto;
            /* Allows table to scroll horizontally on small screens */
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-light);
            border-bottom: 1px solid #edf2f7;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--primary);
        }
        /* Status Pills (In Stock, Low Stock) */
        
        .pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .pill-in {
            background: #f0fff4;
            color: var(--success);
        }
        
        .pill-low {
            background: #fffaf0;
            color: var(--warning);
        }
        /* --- MODAL STYLES --- */
        
        .modal-overlay {
            display: none;
            /* Hidden by default, toggled via JS */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-body {
            background: white;
            width: 90%;
            max-width: 900px;
            max-height: 80vh;
            border-radius: 12px;
            padding: 30px;
            overflow-y: auto;
            position: relative;
        }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        /* --- RESPONSIVE MEDIA QUERIES --- */
        /* Adjustments for Tablets */
        
        @media (max-width: 1024px) {
            .chart-grid {
                grid-template-columns: 1fr;
                /* Stacks charts vertically */
            }
        }
        /* Adjustments for Mobile Phones */
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
                /* Stacks sidebar and main content */
            }
            .sidebar {
                width: 100%;
                height: auto;
                flex-direction: row;
                /* Horizontal navigation on mobile */
                padding: 10px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            .brand {
                display: none;
                /* Hides logo to save space */
            }
            .nav-links {
                display: flex;
                width: 100%;
                overflow-x: auto;
                /* Allows swiping through nav links */
                gap: 5px;
            }
            .nav-links li {
                margin-bottom: 0;
            }
            .nav-links a span {
                display: none;
                /* Shows icons only on mobile */
            }
            .header {
                flex-direction: column;
                height: auto;
                padding: 10px;
                gap: 10px;
            }
        }
    </style>
    <script>
        (function() {
            var s = localStorage.getItem("pos_theme");
            if (s) {
                try {
                    var t = JSON.parse(s);
                    document.documentElement.style.setProperty("--primary", t.primary);
                    document.documentElement.style.setProperty("--primary-hover", t.hover);
                } catch (e) {}
            }
        })()
    </script>
</head>

<body>
    <div class="modal-overlay" id="allItemsModal">
        <div class="modal-body">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3>All Inventory Items</h3>
            <div style="overflow-x: auto;">
                <table id="fullItemsTable"></table>
            </div>
        </div>
    </div>

    <nav class="sidebar" id="sidebar">
        <div class="brand" onclick="toggleSidebar()">
            <img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAgAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIbGNtcwIQAABtbnRyUkdCIFhZWiAH4gADABQACQAOAB1hY3NwTVNGVAAAAABzYXdzY3RybAAAAAAAAAAAAAAAAAAA9tYAAQAAAADTLWhhbmSdkQA9QICwPUB0LIGepSKOAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAAF9jcHJ0AAABDAAAAAx3dHB0AAABGAAAABRyWFlaAAABLAAAABRnWFlaAAABQAAAABRiWFlaAAABVAAAABRyVFJDAAABaAAAAGBnVFJDAAABaAAAAGBiVFJDAAABaAAAAGBkZXNjAAAAAAAAAAV1UkdCAAAAAAAAAAAAAAAAdGV4dAAAAABDQzAAWFlaIAAAAAAAAPNUAAEAAAABFslYWVogAAAAAAAAb6AAADjyAAADj1hZWiAAAAAAAABilgAAt4kAABjaWFlaIAAAAAAAACSgAAAPhQAAtsRjdXJ2AAAAAAAAACoAAAB8APgBnAJ1A4MEyQZOCBIKGAxiDvQRzxT2GGocLiBDJKwpai5+M+s5sz/WRldNNlR2XBdkHWyGdVZ+jYgskjacq6eMstu+mcrH12Xkd/H5////2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAARCAMgBkADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACiiigAooooAKKKKACijNGR60roAzSUZHqKTcPWpc4rqA7FJio/NX+8Pzppuof+ei/nWcsRSjvJDsyaiq/wBsh/vr+dJ9th/vVm8fh1vND5JdizRVT+0If736Gk/tCL3/ACNZPNMKvtofJLsXM0Z96p/2hF7/AJGm/wBpQ+/5VLzbBr7aD2c+xf5pKof2nH/dNJ/aS/8APNqh5zg19sPZz7GjRWd/aY/wCebfpR/aY/55t+lH9t4L+cfsZ9jRorOGpr/wA82/Sl/tNf7rUf21g/5xexn2NCiqI1GH+235Uv9ow+/wCRq1m+Df20L2c+xdoqn/aEPqfyNL/aEP8Ae/Q1azTCv7aDkl2LdFVRewf3v5077ZD/AH1/OtFj8M9poXJLsWM0VALqH/nov50/zk/vD861jiaUtpIXKyXNFMDj1p24etaKcXsxC0UZHrRketVdAFFFFO4BRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFJmloAPwopM+9NLLUOcVuwHUVA1zEvVx+dRm/hH8WfoDXNUx+Hp/FJFKEn0LdFZ7aiO0bVGdRk/ugfjmuCpn+Ch9q5aoz7GpmkJHrWR9smb+JR9BURnmbrIfw4rgqcVYZfCmy1Qkbe9fUUwzxr1YCsUt/eb9TTCy1xT4sf2IFrDeZsm9hH8Qph1CP3/KsjzF9Wo80/wB2uKfFGJfwpIpYZGmdRH/PNvypjai/8KfmazjL/s0nmtXJPiDGy+1YtYePYvnUJP7o/Omm9m/vL+VUfMakLH+9XNLN8ZLebKVCPYtm4m/56H9KTzXbrIfwqrRXPLG4iW8mV7JE5kb+8fzphK1FilxWEqk5bspQSJcrRvWosUVndj5SXzF9ab5i0zNJQPlRJ5q0vmf7NRbaWmHKiTzP9mk8z/ZplFKwuUf5n+zR5n+zTKKA5R3mf7K0eY1NoosHKO3tSiT/AGVplFFh8o/zP9mjzP8AZplFFhco/wAz/Zo8z/ZplFFg5R+//Zo3rTKKB8o/zFo8xajxRinqLlJd60blqPFGKV33Hyok+T/Zpwf+6x/OoMUYq4zlHZi5Cx5sn8Mh/Ani4l/56H9Kq0VvHGV47SZPskXReT/3h+VOF/N/sy/qKq5peaqOa1o7SZDwsOxoDUpf+eY/wC+qXWrj/nmKzN7UeYa7IcU1VvFfezJ4aJqfa5/+ef/AI9S/a5/+ef/AI9WXvajzDWn9tVOyJ+rxNQ3Uv8Az0b8qja9ZfvbT9etYpe1NLtUPOKnRD9mjOxqt1o2qj9kYv8ANIQK1fDvgDxf4umWLRtDupQT/rmXyox7lyQP5/Sup0/4KzM6z+JtcSzH8VtZgNJ9C54Hp0INezgcupSSlJ3ZwV8VKa5VoeJR2ZXqG/KpjJEv3mC/U19Lad8N/BOi7X0/w9ZpKvSTyvMf8WbJFdAt1DCmyKNEHoqgCvSlhaMVaKOCNepLdnyrHpFzPIEht5ZWPAVEJJq5H4F8YTqHTwzqWD03WjqPzIFfW7SFj1pN1Q83w8dotjVB9z5Pb4e+OolDHw1qP4Qlv5CmL8OfGsh4QLWL0Lu/wDQCvra9n8q2lcMVKqSD7YrNSSS8j3R8/dGBz14r5rF8VUqbtFGqoX6nyyvgTxan+t0+4j/AN6SA/zkqQ/DzxGVwNLvG/7dbcfXYK+mCBuHXj1oC/7P867IcV0H8REui+hzPw31ea38E6Za3lxLJNArxB5CzMQGIGT79K37F5L+3aWPcD5x2sD2z61UWyhWeOdE2yhsuy8F/r+lSxQ3VrfzpDL9mRmyqjkAjr1rqhxE5vlmuZeRLor7Jcgjl0uRrSSQ7FCEH0U9a8w8bft4+Bfhpo2peH/B/m6lryq1vHd7SI7d8EEjj5gDzn0Nez3FxJJBskGHVyCrc7a+LP2ktOtLX4iXi2UM6IfnEMww0Rz8y4Pf1Ht70vEDOcbgsJGGHdua/wAhU6alI958WftueJ7q4u4NKKS2Jkzayqi7REFBbJHUMWBH+718X698VV1TU7pU1NYo5VZ41SFQFbB+7njI+h6/T3jwj8Ovi1408Lv45ubG/wDCXhtLc3CRaxNBLcxhW5EB8qFWY8gEtkE8CvnHUbsX2oy3IhW2MhLeSmSqEk8DPP5811cIcFYzMKk8XiZ88X1aSV13Vt13V0tN30CvXjBcqPP01HUA2TqFxn/rqa7fQvjv8UPCtp/Z1p8QL6ynZdrxtMHiPbHPGKyI9LyNpXrXS6X4QgurV5Lm1WWCNdzpuB3ccgAcc4/XAr9Kp4XhGtD3sPT+aS/JnkKpik/iZ9J+HfjrqHw6+G2h6v4y8Salf8AiDy5f7RisVjuJ+ZGEcSkgjaFALY7j0rwj4t/tG+Kfjr4oEk9heaPo8K/6LZNc+WqKD3QdSTnBJ64HQUA0W70+QTrJFqGmybUZ0mQFlY45we5HqKdaW7aPb/ZtMsUji3bixAJBxjB9ef51zY3hrhbG0vZ4uhH0s7fK3+Q4PGQd1Jr0H/D34w+IPAOuWGr6XcNcGzkV/slxI5SQA52sDngjIPHcV9XfEz9pN/GPgDwnFPbqmvXtnJdatMnEheSUPCjYHAC5P4191eE/hj4K8F6bHaad4K8OCMLguunoXPP948k17keDsioSSp4dS9VdfhI9Gnj8TbU/MrT/h4J7QT3UkwMoJCRuXxgepHUd6h/4RyGFdiJJn/ep2Pxr6m+OPwj1SQQS6B4C0SWDX7+a0sp7e2jtxp5WNmIZc8o2MAj+Fa8J0HYJY4re30y2m/jN/LuMnfheTX6Bg+GMqpJulh1J/wB/3n+L1PKqY6rN3ufNXiRJbC0liSHchGfmXrnkc1ydrDcSuBbQPIe+1fSvqXxZpnhDxD5C+HdEsNHv/mjuY4FaZLhT0lTefuNyNrdGGOleV+ENE0jS/FVydeg0bV9HtJfnsbvLtOFGWj3KRnPQFvXnFfkXGWXVMDXm40nCPk97+b/ACPcwuJc1Y5n4d/B3xf8RN51S8sfDmnbd8Us0gnmYHv8pGPoBx3FezaB+z94b0Jbgz+Lrq9mvLV7e6RUbaqsBkpjAzx0OaqXGlQKyWEbEWiLiJc8Ak55965bWLGbT7pU8zy0GAhZsknoM+pqMtxFbiCo5V+WL72stPLY2rTpU48sN/M7p/hx4Bsf9G0zVX1CUD5r65uCN5+gIIFee+JrTQotSmk0i3a0Vj8kT5JQfUnqaveHr2fRXhe4WNpGTdtY7iqk9iCMZH41PqfiWyXzXxBKJMmMLIPlQfjn/wCtX1GEjleElyunZq+jW3ocsqu87M5a1gjimEkzFtpyQD1xyea9l+HHi39nvwXplvdah4V1G/8AE0kyvJfzpLIYxkZVPlAUHg8c55zXk8cr3f7pGO0nap6gH6VYtrCGGHbIHcZ3BQAMZ/mfavTjhpVkpQdvmeVLFpPQ/QXQrjwvqnh7S7mHwXfaJFdJvdDDNJLCc5Klmb+6R+le2fDjxn4W8SeA9V0fw5oMWh6hFJJHcPeQMzlUkJVPmL7cHuVzx9a+E/hn4w1fw34e1LR7rVbrSrC5tFuBBaLG27e7LJ5hkiZsMy5G04HB6DNO0fXtQ8ceMZ7S+upLaIbpUihkKBpCuBuYdyBkknkn614mN4ToVFPEUKajv8S6nqU8ypyiorY/TzS4lgsYFgjjjRUACxDCfhU0zfPXzZ8J/GF9a2N/4f0K6tBqTyyvBHFGTJLHFCzOeudmcAkDjPevf9R1K5sNMl1bUdJvIYGt3njFvPHI+VBYgIpLN15xivm8X4c47DYeNRVr1H0t+b3PUoZvSqy5Lb3K0srMlRb2ry/8A4aH+GX/P1f8A/gA/+NH/AA0P8Mf+fq//APAB/wDGvFjwRnP/AD5/8mX+Z2/27g/5vw/4B6mXqNpK8wP7Q/wxH/L1f/8AgA//AI1RHLB4rl8q3E8+lyHLrIDsV1PG7HHHOO3PFQ+A8Y/5c/8AyZf5E/27hP5vw/4B6hkVG0lUFuEh0G1t/wDnlCqduoApWkrmxWFrYWf7yLRVOpGa0KxYU0yU8OHjKnnBIOOfpRdJmJufnA4+ntUcJXYoY/MCDilFKM1KN0F0crJa3MEy280EsMiAlldCCOAc/wAqdHFdSSiJIJXkbG1VjLEj6CtCCIXNjHfPdQWtj5nFzLnagIyCPl3ZJBwAM57e3kPinxX4mPig2vg/VLPVtOt/Nhu2bUYPMDo/lhhFvLQ7jGxG7jGQeopNrqeJTxVSs2ofedKiKECovC8AAcClCKOMc15boPij4g6Tr3h7T9ftZLqyvL7bJfxWTwwwjGQHJZf3hxjIBPXivaVhWNAqRhFHQKMCp5k9zeWFxNFJy3IRCKAMAAD0FUPC3/IBj/3pP/Rhr5q+In7QHijSPiHfaTomr2Np4btmX7JKbWO4e5cKCWSRlLEBiRyAM4618faxqV1p3iOe41S+e6luZi7TRMdkhznkDjkf5rzsXieZKEVex5OITpT5ZM+4P2tfjL4q8B+ALWLwno1rJ4r1AuLWbUAzLFCBuZxkEBiTgfT8K8kT4hJYWAj1GKOe7jvhHfXHmBVuZiT/AKxsHMj5AJwABk15p8c/EviW70bwr/a2nXlnrH9n5lkuoJFeXYzlQNxzxk4I9/Wu88F/CHQ/HXh7TL3wH4r1S28Tajbrqt1psiGWFJChdl8z5cFWJG4DJrnw2KdKfKloeLXnOUuTdH23onje2fSxrd5pU2l6WIEmuYbm3KcgA7mVQFj+YZBbHynJHFfnB8RfizqekfFi/stGtbqGDTL+az+y3sUhR1YhZGBYfKDg46HGQR0rq9d8X3niLQbqS21y6WKa3mhCLqUq7CwwSMH7w68+prZ+ELfBzxB4haz8S+H9X0fWJ5nSPWtSnnlVXY7i7ABQnzZ+UjA4+UitqvFdXLsTCL5bPa22/TuVHLJVKfNHX07HceGf2dddgfVNRkuvEPiXUXgmFqZRLNLBIFzFMxkCt8mcBlbqDzXC3PgTxCNZsvDni3TNS0O2NtJLYtfxSRhjtLsysVB4GNqHkZJ6ivszTNH8N6L4fs7C2FuqB0Zkt2jJcxkBXaJTuG3KgEdxng9K+Xv2g/iXqs/jmy8CW2qa9H4e0K6T7ZZ3epS7LuQDDGaIZVEA2gAbSBknmtqGcLE4h01G1/PY82pT5W7I5zUP2ctQgspJovEkuq3sUaT/6PbPOsa7fvMq7WK4GcbRVb4hfs5a14N0Gy1+31u91S0a3RJyBLE9qvG4oGMpzzyAOvbmo7n4p+NrCK4K6vqU7W8Bk+y/bp/s+I1+V5VDgH6gdieK6jwN4x8QeNbDQ7nxdrfxBsdPa5V1t4UjnnuJmCiOMeZ8oY7cHJ464rRrETdoHO6UY2ufNl1Y3Iu5oJQ/mLIQwP19frX3b+zFqcMfw5mstNktJLLT7yaCSSGMqhcojPkO7t8xck5Y9a8E134Q+Pp/HsdlPp0ur6m8UNvJqrCBlZrhuqEEsPm64yMMO4r6S/Z78Ea18NJ9X8K65e2N4unSWzLLp8hdAGQqAd6qc9M8c15GaONXDuNS1menh4uMlYm+L/gy11iO21SbTo7qS3lKI7KMpuHKkj65FcAp8T2enKkGq3y2kigJD5gCrjsc+lfcviHTYNb8J3+nXSb4p7d1I7HKkZHXkHBHvX5ixXE8VmLS4urie0SSSSOFZpVjRnYs2FUgAEk9K+XrYCnOb90+kw2YVIaM0rPVvE+n6dYafbeJdVEFlaLBBE1yxATO7HqSSa3NH+IfjLw/eNdaT4u1VW4EiNL5ocDuGB5ryzUrxRZtDHbQ7B1ILbmP1Jr0vwBFrnjzxna+H9I0rSpZ5I2bzMBUiiVdzu/wAmcDrjrivLxuXW970fmdvPKSlJntGl/HfxjpqfZb4WmpxuP9YyBJBj/aXr+K1Zn+Pmr30JdtCs4g64HmXZBz9Qv+NeUav4P8T6NqDadrGlXenzoqsI7iJkLKRkMuQCpwevvU1n8KfHeo2zXlt4c1CSNTt/1JUnHTAIBNfIV+FcBKXNyrff3X+R0xzDFJWbPaH+MD6lb7LbQ4lJH8V1uOfwFYV/4ycSTRX2lB1YlkntZQCCOpKkke9cFbfDXx7ayb7jwtqMn+/Cyk/Qtiu50b4a+LLzS1XUNE1BHIykpgLkH/wAeJrkxHCWCvomvuNIZtiL+9qZ0eu+GNchivbSW0aFnCpLG4bGT1Ge9ep2LxXVkpXMSSJlQGwFHpUHh3wJ4o0qzKXuj3cO8lmMiYyMdMnJro/D/AIaGlyecILqSSaZppWMikZYkgYAA4zgZr3sLwuq11VkmnsfOYrN60qbhY47b/tL+ZGKpySSSHc7Mx9c16r44+Dfid9Qub7RI4r7OZfKDhHH0U7c+1eF3r3CzFLiGSONvvKwKn3BHava+oSipRlfToQsTRlJLc6jw94Mu/EWrWll5sdsly+FkuDhCf7w44HuRXoWpfAHRfHWiatr+r3M1w9jp0V2RbFkWcPMVYdMrja3Iwc9688tNVkMK6Tpkbwbm8q4uEOGiTvHz3b1PQHPbB2dI1uXwz4x0Y6Ndvb3KXVna3uWYhImfcpQ5wGJ4J78Yr5LH5JiatWzjp3PUhiq2GpqS0fY2H/Z21G3tBe6F4htLq1fDwm6iaB2zjHcjPPcda7nw1f+KdL8Pf2Z4oW0tNG0qN7YRQKTNO8XG6V2IHRslVA6nPaul8VanB4dV/EU8kjQXFrbmSOG3yG8qLLK2Mnj5zjHXB7Gue1zRNP1me1vru6AvHgjxGE27gAzD7o5yST27mtMLQxFKhCLldpHFUxkq85TZRtRrHhqxTxXpl1ZWE06ySTaZAUkLLCRgoyMCSAR8u0fTuK0INR0PWJk0rT9Pa9mV4yZJZ2d0CIeSxbJI5wQOM8A46l9T03UdNn0q7t7i5s4XM6xSWzSICRks6BguMnsT1HA4GecNxDpvi2x8P6bLBpBTMqG1ikKXG7+FJGYvGSAc7McjByK9WhiKuHpKEJXt06HNJqTclqbXinxteyWdxZTNcX0U0ayyxyvGI2VhkN5aqCTj15FWrXS7DVJGk0hXvbG3RJBHeyNIqLjqC0mSPm9jXFXVxY6nNcpp95HHhGjuGkVSJU25JUNkMCO4IrU0y7fTbm6upY7m0nhtnZYb/AAlwhBU5BHA2lueRzjntXPOFVzTctfM6oyT06GhoVhpnieTXrC8g1y9NrHBcxI+XijkDHd8p+YZXbyTx0rq9C0m10mO80q2tDcXU7SzTyGRhtY5XzG7nknjg47Vzd1olvHo2vaRpl4bg26WbI2YVfJJAX7i9ueBjniulttYa0OnXmnpafbN0kksEcjkAkZJJ3L3HfoM9RXsUcRKMFFO7f5nBKjJzbtZDPtMWqW1po0VmIruJJJEvJN37pn43E45YYBPQZ9BkTGC2uEiuLO4nnVJTLvt3wqse+cgjbwSDjjp6mt9ptZ1LRlu9Vuri0Ak81Yd4kRcj5R1+Udzj3/E27Sw0ea1u9HuYbFHl8+SeKbapz6kEY4z261s5vl1Oq9jE0+C4EUE1zDqFuglVmvJVKpHIq4j24GSN2fXj8a39OhfT/EWiXH9qXupSpeWSbr0ANJJghVLHAbIHHHbBpv9iXHh9rS7VbmztNMKbkM0TuwkbchRkUZK5wCe2B7VHqer6fepa6vqFg7MmqwvaNbOq7JHQY3ZJDfMrcE4GaHCy06+ZJWR6V418J3+h6hcS3Jga2nupokVH4AV0UAcf7J+vvXCXV/J5N2dJt5Y3WZWklVXVCwBKAggjkDPOPauzuvGEutW+nWr3F5NKJLmKW7lhdmVJMb1x0GVPfrgVyPijUre98Vpp9hbXF/JHdMrS20DkwqY8rGx6AFhkknqT71xyo05Slzb/kc0qMZPmTOp0X4j39te+LIf7XlnmvNMubiS5gRPLtXVy0UXA2k7C3ToWJNdkdJitdH00QWtrFC0VtaS7cPuGBlvTkjr2ya+eItT0ma+nW7iFzaXscltckqPl3SYckMQGG0dD37V9IfALVNE1HxT4V03W7PUJ7DWLRTf6dYxgC3QFiPM3kFg5ORhOue/HLHHUKUlGfzJdKpEi1LT5IFurRbOO2gF3HNPBb43vIk2CjHf6+lFrqSWuqwaXZG3lS7iu3uZdxG7BWMKR0JwxOehI4HGdT4neMNOuNW1A6h4dGpzXSJPJLeSHPkTEgBYztHLAHvwfeua8P8AiuG6u5bO00aOzl/s2aS1nBSPzgm1iFCjH3hj5gRxXJQxkpJRg/Xy6GbhaN2dZczXGl6R/bup3Vx5ltHI1wqgzFBtJ+8TkZJwOO3bpXMi2vtfv4tK8OtquqRwRz+Vb29m8gj8xxjB3ENgnsB6DFRnVNF1Dw9rGnJNqsAiuob9TFI8kEUjHcuHC5QFThlJx8uccV2Nh4etNJa1m0m+1S11KLy5LiW4LxGVFO7aQT8vUDgnnHFKE405Xv6+v8AkEoN6M+wP2WPFFp4x8M3qX0kLW9u8SJPJceQ+baMkB8EHPzkck5z+FJ8SvB+paDrI1ue6hu5p5pLgxCPYF++eFJOPlHtjPWvHfDNnrXgDVbWLwlr8kWpXUzPJdwLIVQRl+AQwGCWBOP7gr3uXU/HVxrnh+98T2dra3F0TcOJMqyRRhiqBlOD8+CQcc889K+bzmjCjRcaCbT89tNf+G2PXySdKT9rXaXoe7eDNQv/ABBplp4m0i3e3VbZWtp1kJBbH3wC2D0weDivd/htqkU91baJdWzRJalg0z4YuXJ7gnoMV80+B7TSTJ4B0vwd9qiujaed5KxSGZ5Fm2sSCGy3ycLtPHpXXWvhGC8+I+saxoU19pTyXBuV8okuXyMgO2Rk4J46ZrGWBn9Qq1E37r0XoZ5jSwuKpQ9lGzXXufoV4e8U6T4mhn8MaFdPdzh2hjggtJpyqx5MwkIXaA2Ow/L0LU5tVitNQK6Rb217J58UEcsjvJJBt+bO/dnHQdM18Wf8ABSL4n3Ni+g/D2zuV8y2dpLkB2IfaQqnjBGSc9fSvFv2Yv2gPCvgLxpqHh3VtZkt7meYC0guGMkO7GGLZJAJHYg96+W4H4VxHF/FVGEOana61trbb0Pd4qzLCcN8N1a9SSUnFqPqfy7n6L3uiaF4j8JXtr4jskvFuLdvl1N3RHJXchBJI+Ulh7EjBGa/GX4u6Rc+D/idqmlRHbFBqE4iibj92sjcYPYA4r9wfjBeXvibxNpmgQ6Q0sd1FeSKiTlnnkVYlUhQoCrkDdnPzdhX4//ALQ8KXPxw8YxyxiJl1eVWUDAzyMj2yODXq+LOAwPDuXYXI8I7znUlUa7KKUfzkzzuAMdis6r182xKahCKgkurbd9fJL8T9g/B11BpmgWxkkSECzAgjYlmMpRAFXqW+YY474xW+Lvy0Jt/LG45fKjpXjNpqHh3w9r1t4p1e8tJLYrDHb3LNvOIgHyxZQ2FKsehz9K6efxHqm+/wBKt1e0e5HnQpM8qBM5JbGOgPbB69K+bxVGGCxEsNdXjt/XQ+mw0XiKEa9t/wDgPsdzqV9c6dpN0mnFRcm3kbYCW2HBYZ+pHc9q+Sfh94BvvEngXwY2gaHdLqNm0moadYfb5LcCRpvKcCFl8oK3JI25GcjGRX2Bo2vWN7q9tBdqjvCodWiZXA5IG5TyAQMjI65r8l/iz+2f+0D8N/E2p6baeGvgprmiLIbaS4g0ua2kiMfABka4ABPB4r43g3KcVm+LxHNOFJKN7y2T72tuz7DP6WFw+CpYPFV1Snzu9no0le+62PoPxx4BhuvFkVnqfhu4n02eCOO/mspFaBXJcKzqQGCHJGRxt7mvN9f+B+pWt5by+BNbS2e3kYRlBJJHIhxhTG6kHBHH5+1fFfhH9v745eHddGn6po3gTxAGkFuLTVbCaOR2bBBDBhjBP4cZr7s/Zz/AGhNG+OGiXlpqWlQ6H4p0hY01LTJ7hJ4izfKZIZVJV43wQU5BIG1sZWv0vG8N4jCyi8LJLl9D5Shj6FX+JFp+aP/2Q==" alt="Luna's Logo">

        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a></li>
            <li><a href="pos_terminal.php"><i class="fa-solid fa-cash-register"></i> <span>New Order</span></a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> <span>Inventory</span></a></li>
            <li><a href="salesreport.php"><i class="fa-solid fa-chart-line"></i> <span>Sales Report</span></a></li>
            <li><a href="analytics.php" class="active"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="customer.php"><i class="fa-solid fa-users"></i> <span>Customers</span></a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>
        </ul>
    </nav>

    <div class="main">
        <header class="header">
            <h2 style="margin:0;">Analytics Overview</h2>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">

                <div style="display: flex; align-items: center; gap: 5px; background: #f8fafc; padding: 5px 10px; border-radius: 6px; border: 1px solid #ddd;">
                    <small style="color: var(--text-light); font-weight: bold;">History:</small>
                    <input type="date" id="historyDate" onchange="filterByDate()" style="border: none; background: transparent; outline: none; cursor: pointer; font-size: 13px;">
                </div>

                <select id="timeFilter" onchange="updateAnalytics()" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd; outline: none; cursor:pointer;">
                    <option value="today">Today</option>
                    <option value="7days" selected>Last 7 Days</option>
                    <option value="month">This Month</option>
                </select>

                <button onclick="downloadCSV()" style="background: var(--primary); color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                    <i class="fa-solid fa-download"></i> Export
                </button>
            </div>
        </header>

        <div class="content">
            <div class="stats-row">
                <div class="stat-card">
                    <small>Revenue</small>
                    <h2 id="stat-sales">₱0.00</h2>
                    <span class="trend" style="color: var(--success)"><i class="fa-solid fa-arrow-up"></i> 12.5%</span>
                </div>
                <div class="stat-card">
                    <small>Total Orders</small>
                    <h2 id="stat-orders">0</h2>
                    <span class="trend" style="color: var(--text-light)">Avg. 45/hr</span>
                </div>
                <div class="stat-card">
                    <small>Avg. Order Value</small>
                    <h2 id="stat-avg-order">₱0.00</h2>
                    <span class="trend" id="stat-avg-order-label" style="color: var(--primary)">per order</span>
                </div>
                <div class="stat-card">
                    <small>Peak Hour</small>
                    <h2 id="stat-peak-hour">--:-- --</h2>
                    <span class="trend" id="stat-peak-traffic" style="color: var(--warning)"><i class="fa-solid fa-fire"></i> No data yet</span>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-box">
                    <h4 id="chart-title">Revenue Trend <span><i class="fa-solid fa-ellipsis-vertical"></i></span></h4>
                    <div style="flex-grow: 1; min-height: 0;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <h4>Sales by Category <span><i class="fa-solid fa-ellipsis-vertical"></i></span></h4>
                    <div style="flex-grow: 1; min-height: 0;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 id="tableHeading" style="margin: 0;">Top Performing Items</h3>
                    <button onclick="showAllItems()" style="background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 12px;">View All Items</button>
                </div>
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Units Sold</th>
                            <th>Popularity</th>
                            <th>Stock Status</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:20px;color:#718096;">Loading data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        let revenueChart, categoryChart;

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        function showAllItems() {
            document.getElementById('fullItemsTable').innerHTML = document.getElementById('itemsTable').innerHTML;
            document.getElementById('allItemsModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('allItemsModal').style.display = 'none';
        }

        // ── All 7 real categories matching the DB ──────────────
        const CATEGORIES = [{
            key: 'Breakfast',
            label: 'Breakfast',
            color: '#5a67d8'
        }, {
            key: 'Merienda',
            label: 'Merienda',
            color: '#4fd1c5'
        }, {
            key: 'Burgers And Sandwiches',
            label: 'Burgers & Sand.',
            color: '#f6ad55'
        }, {
            key: 'Rice Meal',
            label: 'Rice Meal',
            color: '#fc8181'
        }, {
            key: 'Native',
            label: 'Native',
            color: '#68d391'
        }, {
            key: 'Dessert',
            label: 'Dessert',
            color: '#f687b3'
        }, {
            key: 'Drinks',
            label: 'Drinks',
            color: '#76e4f7'
        }, ];

        document.addEventListener('DOMContentLoaded', async() => {
            await requireLogin();

            // Revenue Line Chart
            const revCtx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(revCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: [],
                        borderColor: '#5a67d8',
                        tension: 0.4,
                        fill: true,
                        backgroundColor: 'rgba(90, 103, 216, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#5a67d8',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f0f0f0'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Category Doughnut Chart — all 7 real categories
            const catCtx = document.getElementById('categoryChart').getContext('2d');
            categoryChart = new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: CATEGORIES.map(c => c.label),
                    datasets: [{
                        data: CATEGORIES.map(() => 0),
                        backgroundColor: CATEGORIES.map(c => c.color),
                        borderWidth: 0,
                        hoverOffset: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 8,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            await updateAnalytics();
        });

        // ── Today / 7 Days / This Month dropdown ──────────────
        async function updateAnalytics() {
            const range = document.getElementById('timeFilter').value;
            document.getElementById('tableHeading').innerText = 'Top Performing Items';
            document.getElementById('historyDate').value = '';

            const today = new Date();
            const toDate = today.toISOString().split('T')[0];
            let fromDate = toDate;
            if (range === '7days') {
                const d = new Date(today);
                d.setDate(d.getDate() - 6);
                fromDate = d.toISOString().split('T')[0];
            } else if (range === 'month') {
                const d = new Date(today);
                d.setDate(1);
                fromDate = d.toISOString().split('T')[0];
            }
            await loadAnalyticsData(fromDate, toDate);
        }

        // ── Date picker ───────────────────────────────────────
        async function filterByDate() {
            const selectedDate = document.getElementById('historyDate').value;
            if (!selectedDate) return;
            document.getElementById('tableHeading').innerText = `History for ${selectedDate}`;
            await loadAnalyticsData(selectedDate, selectedDate);
        }

        // ── Core: fetch everything from real API ──────────────
        async function loadAnalyticsData(fromDate, toDate) {
            // ── KPI cards ──
            const report = await api.salesReport.get({
                date_from: fromDate,
                date_to: toDate
            });
            if (report.success) {
                const totalRevenue = parseFloat(report.summary.total_revenue) || 0;
                const totalOrders  = parseInt(report.summary.total_orders)   || 0;

                document.getElementById('stat-sales').innerText  = fmt(totalRevenue);
                document.getElementById('stat-orders').innerText = totalOrders;

                // ── Avg. Order Value = total revenue ÷ total orders ──
                const avgOrderValue = totalOrders > 0 ? totalRevenue / totalOrders : 0;
                document.getElementById('stat-avg-order').innerText = fmt(avgOrderValue);
                document.getElementById('stat-avg-order-label').innerText =
                    totalOrders > 0 ? `÷ ${totalOrders} orders` : 'no orders yet';
            }

            // ── Revenue trend line chart ──
            const trend = await api.dashboard.revenueTrend();
            if (trend.success && trend.data.length) {
                revenueChart.data.labels = trend.data.map(d => {
                    const dt = new Date(d.day);
                    return dt.toLocaleDateString('en-PH', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric'
                    });
                });
                revenueChart.data.datasets[0].data = trend.data.map(d => parseFloat(d.revenue));
                revenueChart.update();
            }

            // ── Category doughnut — count qty sold per category ──
            const top = await api.dashboard.topProducts();
            const tbody = document.querySelector('#itemsTable tbody');
            if (top.success && top.data.length) {
                const maxQty = Math.max(...top.data.map(p => parseInt(p.total_qty)));
                tbody.innerHTML = top.data.map(p => {
                    const pct = Math.round((p.total_qty / maxQty) * 100);
                    return `<tr>
                        <td class="item-name">${p.name}</td>
                        <td>${p.total_qty}</td>
                        <td>
                            <div style="width:100px;height:6px;background:#edf2f7;border-radius:3px;">
                                <div style="width:${pct}%;height:100%;background:var(--primary);border-radius:3px;"></div>
                            </div>
                        </td>
                        <td><span class="pill pill-in">● In Stock</span></td>
                        <td>${fmt(p.total_revenue)}</td>
                    </tr>`;
                }).join('');

                // Build category totals using products list lookup (case-insensitive)
                const products = await api.products.list();
                const catTotals = {};
                CATEGORIES.forEach(c => catTotals[c.key] = 0);
                if (products.success) {
                    const nameToCategory = {};
                    products.data.forEach(p => {
                        nameToCategory[p.name.trim().toLowerCase()] = p.category;
                    });
                    top.data.forEach(p => {
                        const cat = nameToCategory[p.name.trim().toLowerCase()];
                        if (cat && catTotals[cat] !== undefined) {
                            catTotals[cat] += parseInt(p.total_qty);
                        }
                    });
                }
                const totalsArr = CATEGORIES.map(c => catTotals[c.key]);
                const hasData = totalsArr.some(v => v > 0);
                categoryChart.data.datasets[0].data = hasData ? totalsArr : CATEGORIES.map(() => 1);
                categoryChart.data.datasets[0].backgroundColor = hasData
                    ? CATEGORIES.map(c => c.color)
                    : CATEGORIES.map(() => '#e2e8f0');
                categoryChart.update();
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#718096;">No sales data for this period.</td></tr>';
                // Show empty grey donut when no data
                categoryChart.data.datasets[0].data = CATEGORIES.map(() => 1);
                categoryChart.data.datasets[0].backgroundColor = CATEGORIES.map(() => '#e2e8f0');
                categoryChart.update();
            }

            // ── Peak Hour — calculate from today's transactions ──
            if (report.success && report.transactions && report.transactions.length) {
                const hourCounts = {};
                report.transactions.forEach(t => {
                    const hrStr = new Date(t.created_at + ' UTC').toLocaleString('en-US', {
                        timeZone: 'Asia/Manila', hour: 'numeric', hour12: false
                    });
                    const hr = parseInt(hrStr);
                    hourCounts[hr] = (hourCounts[hr] || 0) + 1;
                });
                const peakEntry = Object.entries(hourCounts).sort((a, b) => b[1] - a[1])[0];
                if (peakEntry) {
                    const hr24 = parseInt(peakEntry[0]);
                    const ampm = hr24 >= 12 ? 'PM' : 'AM';
                    const hr12 = hr24 % 12 === 0 ? 12 : hr24 % 12;
                    document.getElementById('stat-peak-hour').innerText = `${hr12}:00 ${ampm}`;
                    document.getElementById('stat-peak-traffic').innerHTML =
                        `<i class="fa-solid fa-fire"></i> ${peakEntry[1]} order${peakEntry[1] !== 1 ? 's' : ''} that hour`;
                }
            } else {
                document.getElementById('stat-peak-hour').innerText = 'No orders';
                document.getElementById('stat-peak-traffic').innerHTML =
                    `<i class="fa-solid fa-clock"></i> No data today`;
            }
        }

        // ── CSV Export ────────────────────────────────────────
        function downloadCSV() {
            let csv = "Item Name,Units Sold,Stock Status,Total Revenue\n";
            document.querySelectorAll("#itemsTable tbody tr").forEach(row => {
                const cols = row.querySelectorAll("td");
                if (cols.length >= 5) {
                    csv += `"${cols[0].innerText}",${cols[1].innerText},"${cols[3].innerText.replace('● ','')}","${cols[4].innerText}"\n`;
                }
            });
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'analytics.csv';
            a.click();
        }
    </script>

    <!-- PWA Registration -->
    <script src="js/pwa.js"></script>
</body>

</html>
