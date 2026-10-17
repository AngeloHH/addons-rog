/**
 * Addons ROG - Translated interface content
 *
 * - Keep editorial copy separate from configuration and runtime data.
 * - Supply matching English and Spanish entries for connected views.
 */

export const contentTranslations = {
  en: {
    admin: {
      dashboard: 'Dashboard',
      stats: 'Stats & downloads',
      accounts: 'Accounts',
      periods: { '7d': '7 days', '30d': '30 days', '90d': '90 days' },
      pendingReview: 'Pending review',
      openReports: 'Open reports',
      activePlayers: 'Active players',
    },
    homeCategories: {
      names: {
        'death-knight': 'Death Knight',
        'demon-hunter': 'Demon Hunter',
        druid: 'Druid',
        hunter: 'Hunter',
        mage: 'Mage',
        monk: 'Monk',
        paladin: 'Paladin',
        priest: 'Priest',
        rogue: 'Rogue',
        shaman: 'Shaman',
        warlock: 'Warlock',
        warrior: 'Warrior',
        alchemy: 'Alchemy',
        archaeology: 'Archaeology',
        blacksmithing: 'Blacksmithing',
        cooking: 'Cooking',
        enchanting: 'Enchanting',
        engineering: 'Engineering',
        'first-aid': 'First Aid',
        fishing: 'Fishing',
        herbalism: 'Herbalism',
        inscription: 'Inscription',
        leatherworking: 'Leatherworking',
        tailoring: 'Tailoring',
        dps: 'DPS',
        pvp: 'PVP',
      },
    },
    profile: {
      titleLabels: {
        novice: { label: 'Novice Scribe', requirement: '1 addon' },
        apprentice: { label: 'Apprentice Scribe', requirement: '3 addons' },
        journeyman: { label: 'Journeyman Scribe', requirement: '10k downloads' },
        master: { label: 'Master Scribe', requirement: '10 addons · 4.5★ avg' },
        grandmaster: { label: 'Grand Master Scribe', requirement: '300 skill' },
      },
      achievementLabels: {
        'first-zip': { title: 'First ZIP', text: 'Publish your first addon.' },
        healer: {
          title: 'Healer main',
          text: 'Publish five addons in the Healer category.',
        },
        'ten-thousand': {
          title: 'Ten thousand hands',
          text: 'A single addon passes 10,000 downloads.',
        },
        'realm-first': { title: 'Realm first', text: 'Be the first uploader for a new client.' },
        'five-stars': {
          title: 'Five stars, five times',
          text: 'Five addons rated 4.5 or higher.',
        },
        'well-read': { title: 'Well-read', text: 'Reply to 100 community requests.' },
        'long-march': { title: 'Long march', text: 'Keep one addon updated across five clients.' },
        'grand-master': { title: 'Grand Master Scribe', text: 'Reach 300 skill in Addon crafting.' },
        treasurer: { title: 'Guild treasurer', text: 'Cover one month of server costs.' },
      },
    },
    about: {
      timeline: {
        folder: {
          title: 'A Discord channel and a shared folder',
          tag: 'Origin',
          text:
            'Angelo and Gaara start collecting the addons the guild passed around in chat. 40 ZIPs, an ' +
            'index in a .txt.',
        },
        launch: {
          title: 'addons.rog is born',
          tag: 'Launch',
          text:
            'First website: PHP, one table and a download button per addon. Wrath of the Lich King only. ' +
            '214 addons in three months.',
        },
        expansions: {
          title: 'Mists and Draenor join the catalogue',
          tag: 'Expansion',
          text: 'Client tags and version history arrive. The Lua error stops being the first question on Discord.',
        },
        uploads: {
          title: 'Anyone can upload',
          tag: 'Community',
          text:
            'Uploads open to every user with manual review. The rules appear: seven articles, the same ones ' +
            'as today.',
        },
        requests: {
          title: 'Requests and role packs',
          tag: 'Product',
          text:
            'People were requesting addons in the comments of other addons. We gave them a place. The ' +
            'Tank/Healer/DPS packs come from the 20 most repeated requests.',
        },
        independence: {
          title: 'No ads, with the community',
          tag: 'Independence',
          text:
            'We removed the only banner there ever was. The server is paid for by voluntary contributions ' +
            'and the founders\' pockets.',
        },
        redesign: {
          title: 'Redesign and four more expansions',
          tag: 'Today',
          text:
            'BfA, Shadowlands, Dragonflight and The War Within. New home page, search with filters and this ' +
            'page you are reading.',
        },
      },
      founderProfiles: {
        angelo: {
          title: 'Co-founder · the server guy',
          role: 'Engineer · Tank',
          biography:
            'Builds and maintains the infrastructure, the account system and the mailbox. If the site loads ' +
            'fast on patch Sunday, that is his doing. Has tanked since Naxxramas and still insists Omen ' +
            'beats any modern meter.',
          tags: ['Wrath', 'Prot Paladin', 'Coffee'],
          bars: { server: 'Server', backend: 'Backend', patience: 'Patience' },
        },
        gaara: {
          title: 'Co-founder · the catalogue guy',
          role: 'Librarian · Healer',
          biography:
            'Reviews every upload, keeps the listings tidy and writes the rules. Has opened more .toc files ' +
            'than anyone alive. Raid healer, author of the site\'s first README and the reason "Load out of ' +
            'date AddOns" is in bold in the guide.',
          tags: ['Mists', 'Disc Priest', 'Tea'],
          bars: { review: 'Review', catalogue: 'Catalogue', diplomacy: 'Diplomacy' },
        },
      },
      achievements: {
        'first-zip': { title: 'First ZIP', text: 'Publish the first addon in the catalogue.' },
        dalaran: { title: 'Library of Dalaran', text: 'Pass 500 published addons.' },
        five: {
          title: 'Five expansions',
          text: 'Have a catalogue on five different clients.',
        },
        adventurers: {
          title: 'Four thousand adventurers',
          text: 'Reach 4,000 verified accounts.',
        },
        'zero-errors': {
          title: 'Zero errors',
          text: 'A full month without a single addon pulled for malware.',
        },
        'no-banners': { title: 'No banners', text: 'Two years running without advertising.' },
        million: { title: 'One million downloads', text: 'Reach 1,000,000 total downloads.' },
        funded: {
          title: 'Guild-funded server',
          text: 'Twelve months in a row covered by contributions alone.',
        },
      },
    },
    rules: {
      articles: {
        clean: {
          title: 'Clean files only',
          text:
            'A ZIP with addon folders (.lua, .xml, .toc, textures). No executables, installers, DLLs or ' +
            'scripts that run outside the game. Everything is scanned before publishing.',
          allowed: 'DBM-Core/, DBM-GUI/ with their .toc and .lua files',
          forbidden: 'A setup.exe "that installs it for you" or a password-protected .rar',
        },
        free: {
          title: 'Free and unlocked',
          text:
            'No paid addons, no active commercial licences, no "cracked" premium services. If it is free at ' +
            'the source, it is free here.',
          allowed: 'The free tier of an addon with a premium option',
          forbidden: 'Unlocked Zygor Elite or any subscription addon',
        },
        credit: {
          title: 'Credit the author',
          text:
            'If you did not write it, name the original author in the listing and leave their header in the ' +
            'files. Your name shows as uploader, not creator.',
          allowed: '"Original author: Tandanu / DBM Team" in the listing',
          forbidden: 'Renaming the addon and signing it as your own',
        },
        version: {
          title: 'Version = client',
          text:
            'The expansion you tag must be the one the .toc declares and the one you tested. An addon filed ' +
            'under LK that only works on BfA gets pulled.',
          allowed: 'Interface: 30300 in the .toc -> WotLK tag',
          forbidden: 'Tagging "all expansions" to get more downloads',
        },
        duplicates: {
          title: 'No duplicates',
          text:
            'If the addon already exists, upload your file as a new version from its page. A new listing is ' +
            'only for a fork with real changes and a different name.',
          allowed: 'HealBot 3.3.5.1 uploaded as a version of HealBot Continued',
          forbidden: 'A second listing "HealBot (working)"',
        },
        honest: {
          title: 'Honest listing',
          text:
            'Describe what it does, not what it promises. Screenshots of the real addon, not the trailer. ' +
            'No keyword stuffing to show up in more searches.',
          allowed: '"Damage meter with per-ability breakdown"',
          forbidden: '"BEST ADDON DPS TANK HEALER PVP PVE 2026"',
        },
        privacy: {
          title: 'No personal data',
          text:
            'Strip SavedVariables, WTF, settings with your account name or any file that does not belong to ' +
            'the addon. It is for your own sake.',
          allowed: 'Only the Interface/AddOns folders',
          forbidden: 'The whole WTF/ folder "just in case"',
        },
      },
      checks: {
        clean: {
          question: 'Does the ZIP contain only addon folders (no .exe, .dll or installers)?',
          failure: 'It contains executables or installers. Cannot be uploaded (Art. 1).',
        },
        free: {
          question: 'Is it free at the source and not "unlocked"?',
          failure: 'It is paid or a cracked build. Cannot be uploaded (Art. 2).',
        },
        author: {
          question: 'Did you write it, or will you name the original author?',
          failure: 'Add the original author to the listing before uploading (Art. 3).',
        },
        tested: {
          question: 'Have you tested it on the expansion you will tag?',
          failure: 'Test it first, or tag only the client where it works (Art. 4).',
        },
        duplicate: {
          question: 'Have you checked whether a listing for this addon already exists?',
          failure: 'Search first; if it exists, upload it as a new version (Art. 5).',
        },
      },
      threatLevels: {
        warning: {
          title: 'Fix it and move on',
          text:
            'Incomplete listing, wrong expansion tag, missing original author. We let you know and the ' +
            'upload pauses until you fix it.',
          howToDrop: 'Edit the listing. Nothing on your record.',
        },
        takedown: {
          title: 'The addon is unpublished',
          text:
            'Duplicate, build that does not work on the tagged client, or a second warning for the same ' +
            'thing. The listing leaves the catalogue.',
          howToDrop: 'You can re-upload a corrected build after 7 days.',
        },
        suspension: {
          title: '30 days without uploading',
          text:
            'Paid addon, no credit after a warning, or three takedowns in a month. You can still download, ' +
            'comment and request.',
          howToDrop: 'Lifts on its own. A well-argued appeal shortens it.',
        },
        ban: {
          title: 'Account closed',
          text:
            'Malware, hidden executables or repeated attempts to sneak in paid files. Everything uploaded ' +
            'is removed and the community is told.',
          howToDrop: 'Does not drop. The only rule with no way back.',
        },
      },
      notices: {
        appeal: {
          kicker: 'Appeal',
          before: 'Every decision can be appealed once by writing to KZKG^Gaara from ',
          link: 'the mailbox',
          after: ', reason "My upload was rejected". Reply within 1-2 days with the full reasoning.',
        },
        author: {
          kicker: 'You are the original author',
          before:
            'If someone uploaded your addon without permission and you want it removed or credited, write to the ',
          link: 'Authors',
          after: ' inbox. Resolved within 48 h, no argument.',
        },
        changes: {
          kicker: 'Rule changes',
          before:
            'Announced 14 days in advance on the home page and Discord. Anything uploaded before a change ' +
            'is never penalised retroactively.',
        },
      },
    },
    upload: {
      reminders: {
        root: 'The addon folder goes at the root of the ZIP, not inside another folder.',
        toc: 'Every folder needs its .toc file with the right ## Interface.',
        private: 'No SavedVariables or personal settings inside.',
        versions: 'If the addon already exists, upload it as a version from its page to avoid duplicates.',
      },
    },
  },
  es: {
    admin: {
      dashboard: 'Resumen',
      stats: 'Estadísticas y descargas',
      accounts: 'Cuentas',
      periods: { '7d': '7 días', '30d': '30 días', '90d': '90 días' },
      pendingReview: 'Pendientes de revisión',
      openReports: 'Reportes abiertos',
      activePlayers: 'Jugadores activos',
    },
    homeCategories: {
      names: {
        'death-knight': 'Caballero de la Muerte',
        'demon-hunter': 'Cazador de Demonios',
        druid: 'Druida',
        hunter: 'Cazador',
        mage: 'Mago',
        monk: 'Monje',
        paladin: 'Paladín',
        priest: 'Sacerdote',
        rogue: 'Pícaro',
        shaman: 'Chamán',
        warlock: 'Brujo',
        warrior: 'Guerrero',
        alchemy: 'Alquimia',
        archaeology: 'Arqueología',
        blacksmithing: 'Herrería',
        cooking: 'Cocina',
        enchanting: 'Encantamiento',
        engineering: 'Ingeniería',
        'first-aid': 'Primeros auxilios',
        fishing: 'Pesca',
        herbalism: 'Herboristería',
        inscription: 'Inscripción',
        leatherworking: 'Peletería',
        tailoring: 'Sastrería',
        dps: 'DPS',
        pvp: 'JcJ',
      },
    },
    profile: {
      titleLabels: {
        novice: { label: 'Escriba novato', requirement: '1 addon' },
        apprentice: { label: 'Escriba aprendiz', requirement: '3 addons' },
        journeyman: { label: 'Escriba oficial', requirement: '10 mil descargas' },
        master: { label: 'Maestro escriba', requirement: '10 addons · media de 4,5★' },
        grandmaster: { label: 'Gran maestro escriba', requirement: '300 de habilidad' },
      },
      achievementLabels: {
        'first-zip': { title: 'Primer ZIP', text: 'Publica tu primer addon.' },
        healer: {
          title: 'Sanador principal',
          text: 'Publica cinco addons en la categoría de sanación.',
        },
        'ten-thousand': {
          title: 'Diez mil manos',
          text: 'Consigue que un addon supere las 10.000 descargas.',
        },
        'realm-first': {
          title: 'Primero del reino',
          text: 'Sé el primero en subir un addon para un cliente nuevo.',
        },
        'five-stars': {
          title: 'Cinco estrellas, cinco veces',
          text: 'Consigue cinco addons con una valoración de 4,5 o más.',
        },
        'well-read': {
          title: 'Bien documentado',
          text: 'Responde a 100 solicitudes de la comunidad.',
        },
        'long-march': {
          title: 'Un largo camino',
          text: 'Mantén un addon actualizado para cinco clientes.',
        },
        'grand-master': {
          title: 'Gran maestro escriba',
          text: 'Alcanza 300 de habilidad en creación de addons.',
        },
        treasurer: {
          title: 'Tesorero de la hermandad',
          text: 'Cubre un mes de gastos del servidor.',
        },
      },
    },
    about: {
      timeline: {
        folder: {
          title: 'Un canal de Discord y una carpeta compartida',
          tag: 'Origen',
          text:
            'Angelo y Gaara empiezan a recopilar los addons que la hermandad compartía por el chat. 40 ZIP, ' +
            'un índice en un .txt.',
        },
        launch: {
          title: 'Nace addons.rog',
          tag: 'Lanzamiento',
          text:
            'Primera web: PHP, una tabla y un botón de descarga por addon. Solo Wrath of the Lich King. 214 ' +
            'addons en tres meses.',
        },
        expansions: {
          title: 'Mists y Draenor se suman al catálogo',
          tag: 'Expansión',
          text:
            'Llegan las etiquetas de cliente y el historial de versiones. El error de Lua deja de ser la ' +
            'primera pregunta en Discord.',
        },
        uploads: {
          title: 'Cualquiera puede subir addons',
          tag: 'Comunidad',
          text:
            'Se abren las subidas a todos los usuarios con revisión manual. Aparecen las reglas: siete ' +
            'artículos, los mismos que hoy.',
        },
        requests: {
          title: 'Solicitudes y packs por rol',
          tag: 'Producto',
          text:
            'La gente pedía addons en los comentarios de otros addons. Les dimos su propio espacio. Los ' +
            'packs de tanque, sanador y DPS nacen de las 20 solicitudes más repetidas.',
        },
        independence: {
          title: 'Sin anuncios, con la comunidad',
          tag: 'Independencia',
          text:
            'Quitamos el único banner que hubo. El servidor se paga con aportaciones voluntarias y con el ' +
            'bolsillo de los fundadores.',
        },
        redesign: {
          title: 'Rediseño y cuatro expansiones más',
          tag: 'Hoy',
          text:
            'BfA, Shadowlands, Dragonflight y The War Within. Nueva portada, búsqueda con filtros y esta ' +
            'página que estás leyendo.',
        },
      },
      founderProfiles: {
        angelo: {
          title: 'Cofundador · el del servidor',
          role: 'Ingeniero · Tanque',
          biography:
            'Construye y mantiene la infraestructura, el sistema de cuentas y el buzón. Si la web carga ' +
            'rápido un domingo de parche, es cosa suya. Tanque desde Naxxramas, todavía insiste en que Omen ' +
            'supera a cualquier medidor moderno.',
          tags: ['Wrath', 'Paladín Protección', 'Café'],
          bars: { server: 'Servidor', backend: 'Backend', patience: 'Paciencia' },
        },
        gaara: {
          title: 'Cofundador · el del catálogo',
          role: 'Bibliotecario · Sanador',
          biography:
            'Revisa cada subida, mantiene el catálogo ordenado y escribe las reglas. Ha abierto más ' +
            'archivos .toc que nadie. Sanador de banda, autor del primer README del sitio y responsable de ' +
            'que «Cargar accesorios antiguos» aparezca en negrita en la guía.',
          tags: ['Mists', 'Sacerdote Disciplina', 'Té'],
          bars: { review: 'Revisión', catalogue: 'Catálogo', diplomacy: 'Diplomacia' },
        },
      },
      achievements: {
        'first-zip': { title: 'Primer ZIP', text: 'Publica el primer addon del catálogo.' },
        dalaran: {
          title: 'Biblioteca de Dalaran',
          text: 'Supera los 500 addons publicados.',
        },
        five: {
          title: 'Cinco expansiones',
          text: 'Cuenta con un catálogo para cinco clientes distintos.',
        },
        adventurers: {
          title: 'Cuatro mil aventureros',
          text: 'Alcanza las 4.000 cuentas verificadas.',
        },
        'zero-errors': {
          title: 'Cero errores',
          text: 'Pasa un mes entero sin retirar ningún addon por malware.',
        },
        'no-banners': { title: 'Sin banners', text: 'Mantén el sitio dos años sin publicidad.' },
        million: {
          title: 'Un millón de descargas',
          text: 'Alcanza 1.000.000 de descargas totales.',
        },
        funded: {
          title: 'Servidor de la hermandad',
          text: 'Cubre doce meses seguidos solo con aportaciones.',
        },
      },
    },
    rules: {
      articles: {
        clean: {
          title: 'Solo archivos limpios',
          text:
            'Un ZIP con carpetas de addons (.lua, .xml, .toc y texturas). Sin ejecutables, instaladores, ' +
            'DLL ni scripts que se ejecuten fuera del juego. Todo se analiza antes de publicarse.',
          allowed: 'DBM-Core/ y DBM-GUI/ con sus archivos .toc y .lua',
          forbidden: 'Un setup.exe «que lo instala por ti» o un .rar protegido con contraseña',
        },
        free: {
          title: 'Gratis y sin desbloqueos',
          text:
            'Sin addons de pago, licencias comerciales activas ni servicios prémium pirateados. Si es ' +
            'gratis en su origen, es gratis aquí.',
          allowed: 'La versión gratuita de un addon con una opción prémium',
          forbidden: 'Zygor Elite desbloqueado o cualquier addon por suscripción',
        },
        credit: {
          title: 'Reconoce al autor',
          text:
            'Si no lo escribiste tú, indica el autor original en la ficha y conserva su cabecera en los ' +
            'archivos. Tu nombre aparece como quien lo sube, no como creador.',
          allowed: '«Autor original: Tandanu / DBM Team» en la ficha',
          forbidden: 'Cambiar el nombre del addon y firmarlo como propio',
        },
        version: {
          title: 'Versión = cliente',
          text:
            'La expansión que indiques debe coincidir con la del .toc y con aquella en la que lo probaste. ' +
            'Se retira un addon marcado para LK que solo funciona en BfA.',
          allowed: 'Interface: 30300 en el .toc -> etiqueta WotLK',
          forbidden: 'Marcar «todas las expansiones» para conseguir más descargas',
        },
        duplicates: {
          title: 'Sin duplicados',
          text:
            'Si el addon ya existe, sube el archivo como una versión nueva desde su página. Una ficha nueva ' +
            'solo corresponde a una variante con cambios reales y otro nombre.',
          allowed: 'HealBot 3.3.5.1 subido como versión de HealBot Continued',
          forbidden: 'Una segunda ficha llamada «HealBot (funciona)»',
        },
        honest: {
          title: 'Una ficha honesta',
          text:
            'Describe lo que hace, no lo que promete. Capturas del addon real, no del tráiler. Sin acumular ' +
            'palabras clave para aparecer en más búsquedas.',
          allowed: '«Medidor de daño con desglose por habilidad»',
          forbidden: '«MEJOR ADDON DPS TANQUE SANADOR PVP PVE 2026»',
        },
        privacy: {
          title: 'Sin datos personales',
          text:
            'Elimina SavedVariables, WTF, ajustes con el nombre de tu cuenta y cualquier archivo ajeno al ' +
            'addon. Es por tu propia seguridad.',
          allowed: 'Solo las carpetas de Interface/AddOns',
          forbidden: 'La carpeta WTF/ entera «por si acaso»',
        },
      },
      checks: {
        clean: {
          question: '¿El ZIP contiene solo carpetas de addons, sin .exe, .dll ni instaladores?',
          failure: 'Contiene ejecutables o instaladores. No se puede subir (Art. 1).',
        },
        free: {
          question: '¿Es gratuito en su origen y no está «desbloqueado»?',
          failure: 'Es de pago o una versión pirateada. No se puede subir (Art. 2).',
        },
        author: {
          question: '¿Lo escribiste tú o vas a indicar el autor original?',
          failure: 'Añade el autor original a la ficha antes de subirlo (Art. 3).',
        },
        tested: {
          question: '¿Lo probaste en la expansión que vas a indicar?',
          failure: 'Pruébalo antes o indica solo el cliente en el que funciona (Art. 4).',
        },
        duplicate: {
          question: '¿Comprobaste si ya existe una ficha de este addon?',
          failure: 'Busca primero; si ya existe, súbelo como una versión nueva (Art. 5).',
        },
      },
      threatLevels: {
        warning: {
          title: 'Corrígelo y sigue adelante',
          text:
            'Ficha incompleta, expansión incorrecta o falta del autor original. Te avisamos y la subida se ' +
            'pausa hasta que lo corrijas.',
          howToDrop: 'Edita la ficha. No queda nada en tu historial.',
        },
        takedown: {
          title: 'El addon deja de estar publicado',
          text:
            'Duplicado, versión que no funciona en el cliente indicado o una segunda advertencia por lo ' +
            'mismo. La ficha sale del catálogo.',
          howToDrop: 'Puedes subir una versión corregida después de 7 días.',
        },
        suspension: {
          title: '30 días sin subir archivos',
          text:
            'Addon de pago, falta de atribución después de un aviso o tres retiradas en un mes. Puedes ' +
            'seguir descargando, comentando y haciendo solicitudes.',
          howToDrop: 'Termina automáticamente. Una apelación bien argumentada puede acortarla.',
        },
        ban: {
          title: 'Cuenta cerrada',
          text:
            'Malware, ejecutables ocultos o intentos repetidos de introducir addons de pago. Se retira todo ' +
            'lo subido y se informa a la comunidad.',
          howToDrop: 'No se reduce. Es la única regla sin vuelta atrás.',
        },
      },
      notices: {
        appeal: {
          kicker: 'Apelaciones',
          before: 'Cada decisión se puede apelar una vez escribiendo a KZKG^Gaara desde ',
          link: 'el buzón',
          after: ', con el motivo «Mi subida fue rechazada». Respuesta en 1-2 días con la explicación completa.',
        },
        author: {
          kicker: 'Eres el autor original',
          before:
            'Si alguien subió tu addon sin permiso y quieres que se retire o que se reconozca tu autoría, ' +
            'escribe al buzón de ',
          link: 'Autores',
          after: '. Se resuelve en 48 h, sin discusión.',
        },
        changes: {
          kicker: 'Cambios en las reglas',
          before:
            'Se anuncian con 14 días de antelación en la portada y Discord. Nunca se penaliza de forma ' +
            'retroactiva lo subido antes de un cambio.',
        },
      },
    },
    upload: {
      reminders: {
        root: 'La carpeta del addon debe ir en la raíz del ZIP, no dentro de otra carpeta.',
        toc: 'Cada carpeta necesita su archivo .toc con el valor correcto de ## Interface.',
        private: 'Sin SavedVariables ni ajustes personales.',
        versions: 'Si el addon ya existe, súbelo como una versión desde su página para evitar duplicados.',
      },
    },
  },
};
