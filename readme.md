# Google sheets connector

> A simple way to bind forms to a google sheet

Create a sheets api service account and add its email to the sheet as a user
(eg: YYY@ZZZ.iam.gserviceaccount.com).

Download credentials and store them in data directory.

Set your env variables or a .env file

```
CREDENTIALS="credentials.json"
SECRET="YOUR_SECRET_HERE"
```

Upload and you are good to go!

## POST/GET sheets

By default, the connector allows read and write to THE FIRST SHEET.

You can restrict this to POST/GET request by prefixing the sheet name with POST_ or GET_.

## Whitelist by host

```
HOSTS='["mywebsite.com"]'
```

## Disable captcha

```
DISABLE_CAPTCHA="true"
```

Not recommended, you are going to get spammed :-)

## Sample form

Check out the demo.html to see how it works.

A captcha is required to avoid spam. The captcha is challenge based.

Elements with a _ as name are not injected.