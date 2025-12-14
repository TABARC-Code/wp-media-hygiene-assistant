<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Admin Focus Mode

This plugin lets me put specific roles into a simplified version of the WordPress admin.

The idea is to give clients and non technical users a calmer environment. Fewer menu items, fewer scary options, less chance of "I clicked something and everything is broken now".

I still keep full control as an admin. Focus mode only touches the users and roles I choose.. etc..

## What it does

When focus mode is enabled for a role:

- The left admin menu is trimmed to a list I define.
- The user can still reach their profile page.
- Optional: the user is redirected to a specific landing page after login.
- Optional: the top "Screen Options" and "Help" tabs are hidden.
- Optional: some admin bar items are removed to quiet the interface.

Admins and any role without focus mode stay untouched.

## Features at a glance

- Per role toggle for focus mode.
- Optional per role login landing page.
- Simple interface to pick which admin menu items stay visible.
- Profile page is always accessible.
- Optional "chrome cleanup" for:
  - Screen options
  - Help tabs
  - WordPress logo and a couple of admin bar items

It does not try to replace the admin. It just trims it.

## Requirements

- WordPress 5.0 or newer.
- PHP 7.4 or newer is recommended.
- Any user roles you want to manage must already exist.

## Installation

1. Clone or download this repository.

   ```bash
   git clone https://github.com/TABARC-Code/wp-admin-focus-mode.git
Place the folder in:

text
Copy code
wp-content/plugins/wp-admin-focus-mode
In the WordPress admin, go to Plugins → Installed Plugins and activate, WP Admin Focus Mode.

Go to Settings → Admin Focus Mode.

Configuration
1. Pick which roles get focus mode
On the settings page you will see, a table of roles. For each role:

Tick Put this role into focus mode if you want that role to see a simplified admin.

Optionally set a Landing page after login.,

Examples:

For editors you might set edit.php so they land on the posts list.

For authors you might set edit.php?post_type=page or another screen you prefer.

If you leave this blank, WordPress will keep its default login redirect behaviour.

You can change this later without affecting content.

2. Choose which menu items survive focus/
Below the roles you will see a table of current admin menu items..

Each row shows:

A checkbox to keep or hide in focus mode.

The human readable label.

The raw menu slug.

Tick the items you want focus users to see. Everything else is removed from the menu for users in focus mode.

The profile screen is always available even if you accidentally hide it. People need somewhere to change their password and their name.

You can adjust this list as your setup changes. The plugin reads the live menu, so newly aded items from other plugins show up here as well.

3. Admin chrome cleanup
At the bottom is a tick box:

Hide screen options and help tabs for users in focus mode

If this is enabled, focus users:

Do not see the Screen Options tab.

Do not see the Help tab.

See a slightly tidied admin bar.

This keeps curiosity clicks to a minimum and makes the page feel less busy.

Admins and any role not in focus mode still see the normal full admin.

How it behaves in practice
Menu trimming
When focus mode is active for a user, the plugin waits for WordPress to build the full admin menu, then removes anything that is not on your allowed list.

This only affects the admin menu:

- It does not block direct access to screens by URL.
- It does not change capabilities.
- If someone knows the right URL and has the capability, they can still get there. The goal here is simplicity, not security.
- If you want hard access control, you still need a capability or role management plugin. This one plays nicely alongside those.

Login redirect
If you set a landing page for a role:

On login, that role will be redirected to that page.

You can use a relative admin path like edit.php or edit.php?post_type=page.

Or you can use a full URL if you prefer.

If you leave the field empty, WordPress does whatever it normally does.

Only roles with focus mode enabled and a landing page configured are affected.

Admin bar and chrome
When chrome tidy mode is on for focus roles:

Screen options are hidden.

The top right help tab is hidden.

The WordPress logo, updates and comments icons are removed from the admin bar for those users.

This gives a quieter visual frame without changing any content.

Safety notes
If you accidentally hide too many menu items for a role, you can always log in as an admin and widen the menu again.

The plugin never removes capabilities, it only hides parts of the interface.

The profile page is always available regardless of your menu choices.

On multi user sites it is worth testing a role in a separate browser or incognito window before rolling it out to everyone.

Roadmap and ideas
Things I may add later:

Per user overrides on top of role rules.
A simple "Focus mode on or off" toggle in each user profile.
A temporary bypass link for administrators to view the admin as a specific role.
Optional integration with a local welcome screen for focus users.
If you have a specific use case that neds a slightly different control, you can file an issue and I can wire it into a future version.

Changelog.
See CHANGELOG.md for version history.

## 1.0.8

- First public version.
- Added settings page under **Settings → Admin Focus Mode**.
- Role based focus mode toggle:
  - For each role I can enable or disable focus mode.
  - Optional per role login landing page.
- Menu trimming:
  - Settings page lists currnt admin menu items with slugs.
  - I can choose which ones remain visible for focus roles.
  - Profile screen is always kept.
- Admin chrome tidy mode:
  - Optional hiding of Screen Options and Help tabs.
  - Optional removal of selected admin bar nodes (WordPress logo, updates, comments).
- Behaviour is limited to focus roles:
  - Admins and roles without focus mode stay unchanged.
- Licensed under GPL-3.0-or-later.


WP Admin Focus Mode
Copyright (c) 2025 TABARC-Code

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/gpl-3.0.html>.

