# EVS Vloerverwarming Offerte Plugin

Een professioneel WordPress plugin voor het beheren van vloerverwarming offertes en facturen.

## 📋 Functionaliteiten

- **Multi-step Offerte Formulier** - 8-staps formulier voor klanten
- **Automatische Prijsberekening** - Dynamische prijsberekening op basis van oppervlakte en opties
- **Admin Dashboard** - Volledig beheer van offertes en facturen
- **Email Notificaties** - Automatische emails naar klanten en beheerders
- **PDF Generatie** - Professionele PDF offertes en facturen
- **WordPress Integratie** - Volledig geïntegreerd met WordPress

## 🚀 Installatie

### Stap 1: Plugin Uploaden
1. Zip de hele `EVS` folder
2. Ga naar WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload de zip file en activeer de plugin

### Stap 2: Database Setup
De plugin maakt automatisch de benodigde database tabellen aan bij activatie:
- `wp_evs_offertes` - Voor offertes
- `wp_evs_invoices` - Voor facturen

### Stap 3: Configuratie
1. Ga naar **EVS Offertes** → **Instellingen**
2. Configureer:
   - Admin email adres
   - Bedrijfsnaam
   - Email instellingen

### Stap 4: Shortcode Toevoegen
Voeg de shortcode toe aan een pagina of post:
```
[evs_offerte_form]
```

## 📁 Bestandsstructuur

```
EVS/
├── evs-vloerverwarming-offerte-improved.php (Hoofdbestand)
├── includes/                               (Core classes)
│   ├── class-evs-admin-manager.php
│   ├── class-evs-database-manager.php
│   ├── class-evs-email-service.php
│   ├── class-evs-form-handler.php
│   └── class-evs-pricing-calculator.php
├── admin/                                  (Admin functionaliteit)
│   ├── class-evs-invoices-list-table.php
│   ├── class-evs-offertes-list-table.php
│   ├── class-evs-pdf-generator.php
│   └── views/
├── templates/                              (Template bestanden)
│   ├── forms/quote-form.php
│   ├── emails/
│   └── admin/
└── assets/                                 (CSS/JS bestanden)
    ├── css/
    └── js/
```

## 🔧 Technische Details

### Vereisten
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+

### Database Schema
**Offertes Tabel (`wp_evs_offertes`):**
- Klantgegevens (naam, email, adres)
- Project details (verdieping, vloertype, oppervlakte)
- Prijsberekening (drilling_price, sealing_price, total_price)
- Status tracking

**Facturen Tabel (`wp_evs_invoices`):**
- Koppeling aan offerte
- Factuurnummer generatie
- BTW berekening
- Status beheer

### Security Features
- Nonce verificatie voor alle forms
- Data sanitization en validatie
- SQL injection preventie
- XSS protection

## 📧 Email Templates

De plugin gebruikt moderne email templates:
- **Admin Notificatie** - Bij nieuwe offerte aanvraag
- **Klant Bevestiging** - Bevestiging van ontvangen aanvraag
- **Offerte Email** - Definitieve offerte naar klant
- **Factuur Email** - Factuur verzending

## 🎨 Frontend Formulier

Het formulier bevat 8 stappen:
1. **Verdieping** - Selectie van verdieping
2. **Vloertype** - Type vloer selectie
3. **Oppervlakte** - Vierkante meters invoer
4. **Warmtebron** - Type verwarmingsbron
5. **Verdeler** - Verdeler aansluiting
6. **Vloer Behandeling** - Dichtsmeren optie
7. **Montagedatum** - Gewenste datum
8. **Contactgegevens** - Klant informatie

## 🔄 Workflow

1. **Klant** vult formulier in op website
2. **Systeem** berekent automatisch prijs
3. **Admin** ontvangt notificatie email
4. **Klant** ontvangt bevestiging
5. **Admin** kan offerte bewerken en versturen
6. **Factuur** kan gegenereerd worden vanuit offerte

## 🛠️ Onderhoud

### Logs
Alle errors worden gelogd in WordPress error log.

### Updates
Plugin versie wordt automatisch gecontroleerd bij updates.

### Backup
Maak regelmatig backup van:
- Database tabellen
- Uploaded bestanden
- Plugin configuratie

## 📞 Support

Voor support en vragen:
- Email: support@zee-zicht.nl
- Website: https://zee-zicht.nl

## 📄 Licentie

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
