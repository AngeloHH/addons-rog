# How to install addons

## In 30 seconds

An addon is a folder with `.lua`, `.xml` files and a `.toc`. Installing it means
copying that folder to the right place and enabling it on the character screen.

1. **Download the ZIP** — pick the version for your client.
2. **Unzip into AddOns** — the folder goes straight in, not nested.
3. **Enable in game** — AddOns button on character select.

## Find the AddOns folder

Every addon lives in `Interface/AddOns` inside your client folder. Find the row
for your system and your client:

| System | Client | Path |
| --- | --- | --- |
| Windows | Classic (up to WoD) | `C:\Program Files (x86)\World of Warcraft\Interface\AddOns` |
| Windows | Modern (BfA onwards) | `C:\Program Files (x86)\World of Warcraft\_retail_\Interface\AddOns` |
| macOS | Classic (up to WoD) | `/Applications/World of Warcraft/Interface/AddOns` |
| macOS | Modern (BfA onwards) | `/Applications/World of Warcraft/_retail_/Interface/AddOns` |

If `Interface` or `AddOns` do not exist, create them yourself with that exact
name. The game picks them up anyway.

> **Note** On a private server the folder is named whatever the launcher left it
> as, often without `_retail_`. Find `Wow.exe`: `Interface` sits next to it.

## Copy the addon

1. **Unzip the ZIP** — right-click › *Extract here*. You get one or more folders,
   for example `DBM-Core` and `DBM-GUI`.
2. **Move them into AddOns** — drag *all* the folders into `Interface/AddOns`.
   They must sit at the same level as the others, never inside another folder.

```text Correct
AddOns/
├── DBM-Core/
│   └── DBM-Core.toc
├── DBM-GUI/
└── HealBot/
```

```text Wrong
AddOns/
└── DBM-9.2.3-BfA/
    ├── DBM-Core/
    └── DBM-GUI/
```

3. **Check the .toc** — each folder must contain a file with the same name and
   the `.toc` extension. Without it the game will not see the addon.

> **Heads up** If the game was open, close it fully — or at least return to the
> character screen — before copying. Addons load when you enter with a character.

## Enable the addon in game

1. **Open the game and go to character select** — the `AddOns` button appears
   bottom left. If it does not, there is no valid folder in `AddOns`: check the
   previous step.
2. **Tick the addons you want** — enable them for all characters or just the
   current one with the dropdown at the top.
3. **Tick "Load out of date AddOns"** — it is the checkbox at the top left. Many
   addons here show as out of date only because their `.toc` lists a different
   sub-version.

> **Note** In game, `/reload` reloads the UI without logging out. Use it after
> changing an addon's settings.

## Update or remove an addon

**Update.** Delete the old addon folders and copy the new ones. Your settings are
safe: they live in `WTF/Account/…/SavedVariables`, not in `AddOns`.

**Uninstall.** Remove the folder from `AddOns`. To wipe its settings too, delete
the `.lua` with its name in `SavedVariables`.

## Common problems

### The AddOns button does not appear on character select

There is no folder with a valid `.toc` in `Interface/AddOns`. Most common cause:
the folders ended up inside another one named after the ZIP. Move them up to the
`AddOns` level.

### The addon is greyed out as "out of date"

Its `.toc` declares a different client sub-version. Tick "Load out of date
AddOns" at the top left of the AddOns window; 95% of the time it just works.

### Lua errors when logging in

Almost always a build for another expansion. Check on the addon page that the
expansion tag matches your client and download the right build from the version
history.

### I installed a new version and now the bars are duplicated

Leftovers from the previous version. Delete all the addon's folders in `AddOns`
and copy only the ones from the new ZIP. Your settings stay in `WTF`.

### I want the same settings on another character

Copy the addon's `.lua` file from
`WTF/Account/YOURACCOUNT/Realm/Character/SavedVariables` to the same place for
the other character, with the game closed.

## Quick glossary

| Term | What it is |
| --- | --- |
| `.toc` | The addon's table of contents: name, interface version and list of files to load. |
| `Interface` | The client number the addon declares (e.g. `30300` = 3.3.x, `80300` = 8.3.x). |
| `SavedVariables` | Your per-addon settings, stored in `WTF/`. They survive updates. |
| Lua error | Red window on load. Almost always an addon build that does not match your client. |
| `/reload` | Reloads the UI without leaving the game. |
