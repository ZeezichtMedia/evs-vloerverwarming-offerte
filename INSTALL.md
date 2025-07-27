# 🚀 EVS Plugin Installatie Gids

## Snelle Installatie

### 1. Plugin Uploaden naar WordPress
1. **Download** het bestand: `evs-vloerverwarming-plugin-ready.zip`
2. **WordPress Admin** → **Plugins** → **Add New** → **Upload Plugin**
3. **Upload** de ZIP file en klik **Install Now**
4. **Activate** de plugin

### 2. Eerste Configuratie
1. Ga naar **EVS Offertes** in het WordPress menu
2. Klik op **Instellingen**
3. Vul in:
   - **Admin Email**: jouw@email.nl
   - **Bedrijfsnaam**: EVS Vloerverwarmingen
   - **Auto-send**: Aanvinken voor automatische emails

### 3. Formulier Toevoegen aan Website
1. **Maak nieuwe pagina** of **bewerk bestaande pagina**
2. **Voeg shortcode toe**:
   ```
   [evs_offerte_form]
   ```
3. **Publiceer** de pagina

## ✅ Plugin Functies

### Voor Klanten:
- **8-staps formulier** voor offerte aanvraag
- **Automatische prijsberekening**
- **Email bevestiging**
- **Responsive design**

### Voor Admin:
- **Offerte beheer** - Bekijk en bewerk alle offertes
- **Factuur generatie** - Maak facturen van offertes
- **Email systeem** - Verstuur offertes en facturen
- **Dashboard** - Overzicht van alle activiteiten

## 🔧 Troubleshooting

### Plugin activeert niet?
- Check PHP versie (minimaal 7.4)
- Check WordPress versie (minimaal 5.0)
- Controleer error logs

### Formulier toont niet?
- Controleer of shortcode correct is: `[evs_offerte_form]`
- Check of plugin geactiveerd is
- Controleer theme compatibiliteit

### Emails komen niet aan?
- Ga naar **EVS Offertes** → **Instellingen**
- Controleer email configuratie
- Test WordPress mail functie

### Database errors?
- Plugin maakt automatisch tabellen aan
- Bij problemen: deactiveer en heractiveer plugin

## 📧 Support

Voor vragen of problemen:
- **Email**: support@zee-zicht.nl
- **Check logs**: WordPress Admin → Tools → Site Health

## 🎯 Volgende Stappen

Na installatie:
1. **Test het formulier** op de frontend
2. **Vul een test offerte in**
3. **Controleer admin dashboard**
4. **Test email verzending**
5. **Configureer styling** indien nodig

---

**Plugin Status**: ✅ Klaar voor productie
**Versie**: 4.8.0
**Laatste Update**: Januari 2025
