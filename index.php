<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>DentexaPro | Soluciones para Clínicas Dentales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🦷%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Roboto', sans-serif;
        }
        .hero-section {
          
            background-image: url('assets/img/mi-fondo-hero.webp');
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
            /* background-attachment: fixed; /* Descomenta si quieres efecto parallax */
            height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #fff;
            text-align: center;
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, rgba(10, 30, 75, 0.7), rgba(50, 80, 130, 0.75));
            z-index: 0;
        }
        .hero-content {
            position: relative;
            z-index: 1;
            padding: 2rem;
            max-width: 800px;
            animation: fadeInHeroContent 1.2s ease-out forwards;
        }
        @keyframes fadeInHeroContent {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hero-logo {
            max-width: 500px;
            height: auto;
            margin-bottom: 1.5rem;
        }
        .hero-subtitle {
            font-size: 1.4rem;
            font-weight: 300;
            margin-bottom: 2.5rem;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
            color: rgba(255,255,255,0.9);
        }
        .cta-buttons .btn {
            padding: 0.9rem 2.2rem;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 50px;
            transition: transform 0.2s ease-out, box-shadow 0.2s ease-out, background-color 0.2s ease-out, color 0.2s ease-out;
            margin: 0.5rem;
            min-width: 260px;
        }
        .btn-admin-panel {
            background-color: #fff;
            color: #0d6efd;
            border: 2px solid #fff;
        }
        .btn-admin-panel:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.25);
            background-color: #f0f8ff;
            color: #0a58ca;
            border-color: #f0f8ff;
        }
        .btn-patient-portal {
            background-color: transparent;
            color: #fff;
            border: 2px solid #fff;
        }
        .btn-patient-portal:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.2);
            background-color: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.9);
            color: #fff;
        }
        .cta-buttons .btn i {
            margin-right: 0.75rem;
        }
        .hero-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 1rem;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.7);
            z-index: 1;
        }
        @media (max-width: 768px) {
            .hero-logo { max-width: 280px; }
            .hero-subtitle { font-size: 1.2rem; }
            .cta-buttons .btn { padding: 0.8rem 1.8rem; font-size: 1rem; min-width: 240px; display:block; width: 80%; margin-left:auto; margin-right:auto;}
        }
         @media (max-width: 576px) {
            .hero-logo { max-width: 220px; }
            .hero-subtitle { font-size: 1rem; }
         }
    </style>
</head>
<body>
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-content container">

            <img src="assets/img/logo_dentexapro.png" alt="DentexaPro Logo" class="hero-logo">

            <p class="hero-subtitle">La Solución Inteligente para la Gestión de tu Clínica Dental</p>

            <div class="cta-buttons mt-4">
                <a href="portal/login.php" class="btn btn-patient-portal">
                    <i class="fas fa-user-circle"></i>Portal del Paciente
                </a>
                <a href="login.php" class="btn btn-admin-panel">
                    <i class="fas fa-user-shield"></i>Acceso Profesional
                </a>
            </div>
        </div>
        <div class="hero-footer">
            <p>&copy; <?php echo date("Y"); ?> DentexaPro. Todos los derechos reservados.</p>
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>