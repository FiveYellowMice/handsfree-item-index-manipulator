# Handsfree Item Index Manipulator

I want to list all my food items inside a spreadsheet, along with where they are put and when they expire, so that I can see what stuff I have easily, I can find them easily, and they won't go bad without me noticing.

![A list of food items](https://che.fym.moe/images/2021/01/20/food-list.png)

I'll add items when I buy new stuff, and take them away when I consume them. This list is in a Google Sheet, so I need to take out my phone to edit it.

But these are food items, so a common scenario is when I want to edit the list while I am in the kitchen, halfway through cooking, with wet or grubby hands. It's annoying to operate a phone with wet or grubby hands. So I thought, maybe I can yell to the Google Assistant on my phone to let it do it for me, so this project is the product of this thought.


## What This Does

In an item index spreadsheet (like the one in the above screenshot), it can let you do the following with voice commands through Google Assistant:

* Read a field (e.g. the amount, expiry date or location) of an item.
* Change, increase or decrase a numerical value.
* Remove items from the index.

It can't add items or change non-numerical values at the moment, those still has to be done manually.


## Usage

First you need to set up (login and designate spreadsheets), you'll need to use a device that can copy and paste stuff from a browser, smart speakers won't do:

1. Create a spreadsheet on Google Sheets. The first row defines the columns. There has to be a column named "Name", others can be anything.
2. To the Google Assistant on your phone, say "Talk to Item Index Manipulator".
3. Say "Link Google account", follow the given instructions to allow access to your spreadsheets. You need to go through a browser and then paste the authentication code to the dialog box.
4. Say "Link spreadsheet", name your spreadsheet, then paste the URL of Google Sheets document (e.g. `https://docs.google.com/spreadsheets/d/9KkaWfYeoVXRnHMUm4TZamRzkcciZLMmf_aJJPJxkIi4m/edit#gid=0`).

Also:

* If you want it to forget the spreadsheet you have previously linked, say "Unlink spreadsheet \[name of the spreadsheet\]".
* If you forget what spreadsheets you have, say "List spreadsheets".
* To quit, say "Quit", "Cancel", or something like those.

After it has been set up, you can use it on other devices, as long as you start your conversation with a "Talk to Item Index Manipulator" command. Then, to select or switch the spreadsheet to work on, either list them and choose one from the list, or say "Open \[name of the spreadsheet\]". You can also say "Talk to Item Index Manipulator to open \[name of the spreadsheet\]" in one command.

The following table examplifies the voice commands you can issue (using the spreadsheet in the screenshot from the begginning):

| What you say                             | What happens in the spreadsheet | What you hear                                                        |
|------------------------------------------|---------------------------------|----------------------------------------------------------------------|
| Read the amount of carrot                | -                               | The amount of carrot is 2                                            |
| Read the expiry date of apple            | -                               | The expiry date of apple is 2020-12-03                               |
| Read the amount of a non-existent item   | -                               | Cannot find a row with name "a non-existent item" in the spreadsheet |
| Change the amount of carrot to 3         | B2 becomes 3                    | The amount of carrot has been changed from 2 to 3                    |
| Increase the amount of apple by 1        | B3 becomes 2                    | The amount of apple has been increased from 2 to 3                   |
| Decrease the amount of spring onion by 2 | B4 becomes -1                   | The amount of spring onion has been decreased from 1 to -1           |
| Remove spring onion                      | Row 4 disappears                | Spring onion has been removed                                        |


## Server Setup

1. Copy `server/config.example.php` to `server/config.php`, fill out the values accordingly.
2. Copy `actions-console/settings/settings.example.yaml` to `actions-console/settings/settings.yaml`, adjust values to your liking.
3. `cd server; composer install`
4. Run `make`.
5. Download `gactions`, set it up, `cd actions-console; gactions push`.
