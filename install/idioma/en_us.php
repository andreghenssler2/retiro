<?php
$ecolhaidioma = "Choose a language";
$idioma_selecionado = "Please choose a language for the installation. This language will also be used as the site’s default language, although it can be changed later.";

$checked_lang = "I am sure I want to install the system in this language.";
$proximo = "Next";
$voltar = "Back";

$informacoes_sistema = "System Information";
$informacoes_sistema_descrica = "<p>
    This system was developed to facilitate the creation and management of events in low-demand locations,
    serving various purposes, such as:
</p>

<ul>
    <li>Corporate events</li>
    <li>Meetings</li>
    <li>Community activities</li>
    <li>Religious events</li>
    <li>Other activities that require simple and efficient scheduling</li>
</ul>";

$objetivo = "System Objectives";
$objetivo_descicao = "<p>
    The main objective is to provide an <strong>easy-to-use</strong> tool, allowing anyone to install and configure the system without difficulty.
</p>

<p>
    The system can be installed on <strong>any hosting server</strong> that provides:
</p>

<ul>
    <li>Internet connection</li>
    <li>PHP support</li>
    <li>Compatible database (MySQL, MariaDB, etc.)</li>
</ul>

<p>
    This allows the user to quickly configure the environment and start managing their events in a practical and intuitive way.
</p>";


$recursos = "Additional Features";
$recursos_descricao ="<p>
    The system also offers additional features, such as:
</p>

<ul>
    <li>User-friendly and responsive interface</li>
    <li>Customization options</li>
    <li>Multi-language support</li>
    <li>Event reports and statistics</li>
</ul>

<p>
    These features aim to enhance the user experience and facilitate event management.
</p>";

$confi_install = "System Settings";
$confi_install_info ='<p>Now we will begin configuring our system. At this moment, you are on step 1 of 4 configuration steps.</p>
<p>This step consists of setting up the database information, the location where data will be stored, and the actions that the user or you as the administrator will perform.</p>
<p>Please note that the system supports <b>relational databases (MySQL, MariaDB, etc.)</b>. For other data systems, such as non-relational databases, please contact our support.</p>
<p>
    The database consists of:
</p>
<ul>
    <li><strong>host</strong>: the address where the database will be stored. If you are using the system locally, use localhost or 127.0.0.1. If it is online, enter your website’s IP address for better performance.</li>
    <li><strong>user</strong>: the name of the user responsible for managing the database</li>
    <li><strong>password</strong>: the password of the user responsible for managing the database</li>
    <li><strong>Database Name</strong>: the name of the database where changes will be stored</li>
    <li><strong>Port</strong>: the port through which the IP is allowed to access the database, usually 3306</li>
</ul>
<p>Enter your information below to confirm the database. The next step will only be unlocked after this configuration is completed.</p>
';
$caminho_install = "System Path Configuration";
$caminho_install_info = "<p>Let’s configure the system path.</p>
<p>The system path is the physical location where the system is installed on the server.</p>
<p>It is important to correctly configure the system path to ensure that all resources and functionalities work properly.</p>
<p>Usually, the system path is automatically defined during installation, but in some cases it may need to be adjusted manually.</p>
<p>Make sure that the system path points to the correct directory where the system files are located.</p>
<p>If you are experiencing issues related to the system path, check the server settings or consult the system documentation for additional guidance.</p>
<p>
    After configuring the system path, you will be able to proceed to the next installation step.
</p>
<p>
    <b>Please note that it is not possible to change the system path after installation.</b>
</p>";
?>