=== Gravity Forms - Choice Inventory ===
Contributors: Refactor, Wade
Tags: gravity forms, inventory, choices, image choices, limits
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.8.0
License: MIT
License URI: https://github.com/refactorau/gf-choice-inventory/blob/main/LICENSE

Per-choice inventory limits for Gravity Forms choice fields (Radio/Checkbox/Select, incl. image choices). Field editor UI, optional allow sold-out submissions, entry tagging & list column, and an optional hidden field status for conditionals.

== Installation ==

1. Upload the `gf-choice-inventory` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. In the form editor, open a Radio/Checkbox/Select field → “Choice Inventory” section.

== Usage ==

- Enable inventory for a field.
- Optionally set a custom "Sold-out message".
- Optionally allow submissions with sold-out choices.
- Set per-choice limits (blank = unlimited; <= 0 = immediately sold-out).
- Entries will record any sold-out choices at submit time and display a summary in the Entries table and entry details.

=== Hidden Field Status (for conditionals & notifications) ===

This plugin can write a per-field **status** at submission time so you can use it in **conditional logic**, **notifications**, and **workflows**.

**How it works**  
Add a **Hidden** field (or a Text field you keep hidden) and set its **Field Settings → Advanced → `Parameter Name`** (aka `inputName`) to:

    gfci_{FIELD_ID}_status

Where `{FIELD_ID}` is the numeric ID of the choice field that has inventory enabled. On submit, the plugin will set the hidden field value to either:

- `available` — none of the selected choices for that field were sold-out at submit time.
- `soldout` — at least one selected choice for that field was already sold-out at submit time (based on limits and counts *before* the new entry).

**Example**  
If your ticket field has ID **7**, create a hidden field with `inputName`:

    gfci_7_status

You can then configure conditional logic like “Send admin notification if `gfci_7_status` *is* `soldout`”, or show a message section only when the value equals `soldout`.

**Notes**  
- The status reflects the state **just before** the entry was saved (the plugin subtracts the new entry when comparing against the limit).
- Works with **Radio**, **Checkbox**, and **Select** fields (including Image Choices).
- If limits are blank (unlimited), the status will be `available`.
