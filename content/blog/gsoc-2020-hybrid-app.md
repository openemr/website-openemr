---
title: "GSoC 2020: Hybrid Mobile App"
author:
  name: Amit Meena
  twitter: im_amyth
date: 2020-08-25T00:00:00+05:30
draft: false
slug: gsoc-2020-hybrid-app
images:
  - images/flutter.jpg
coverImage: images/flutter.jpg
coverImageStyle: full
---
A great summer concludes now! Here’s the final report of my journey with OpenEMR in the summer of 2020.
<!--more-->

## Overview
The objective of the project was to develop a mobile application that can not only avail OpenEMR features to increase accessibility but can also unlock a large range of medical usage which needs image processing or hardware which can be found in the daily use smartphone. It is split into two repositories, [openemr/app-golang-openemr](https://github.com/openemr/app-golang-openemr/tree/c6930bb8f84e572234daaa071add316334a247f5) contains the go server which can be used to create an independent WebRTC server and it also has an API endpoint for OCR. [openemr/app-flutter-openemr](https://github.com/openemr/app-flutter-openemr/tree/509a535cb0713c89e8742c7a1de64ddce2a1a2bf) covers the flutter hybrid app, also it uses the [openemr/app-golang-openemr](https://github.com/openemr/app-golang-openemr/tree/c6930bb8f84e572234daaa071add316334a247f5) as a submodule. Installation steps for the project can be found at [README.md](https://github.com/openemr/app-flutter-openemr/blob/509a535cb0713c89e8742c7a1de64ddce2a1a2bf/README.md)

*Note: Links point to the commit made by end of summer(v2.1.1) instead of master.

## Mentors
- Brady Miller
- Stephen Waite
- Rachel Ellison
- David Vu
- Asher Densmore-Lynn

## Project Objectives
The primary objectives of the project were:
- In-App Telehealth (Completed)
- Medicine recognition (In Progress)

The secondary objectives of the project were:
- Barcode/QRcode scanner (Completed)
- Heartbeat Measurement (Completed)
- List and Add Patient (Completed)
- Extending chat support to OpenEMR WebApp (Future Work)
- Facial Expression Detection (Future Work)

## Summary
This section contains the per phase summary of the work done, along with the links to the respective commits.

### Phase 1
The first phase started with the search for the available tech, that can be used to achieve my goals. By the end of this phase, I was done with the `Heartbeat Measurement`, `OpenEMR API` integration, and prototype for `Telehealth` and `Code scanner`.

The commits are as follows:

openemr/app-flutter-openemr
- In the [commit#Initial commit](https://github.com/openemr/app-flutter-openemr/commit/8193da061ad6ea5a2f4462a063297715ba88ccfc), I initialized the flutter project.
- Then, A prototype for `Code Scanner` was added in the [commit#QR Code scanner added](https://github.com/openemr/app-flutter-openemr/commit/58b3ed2875883483fd053be4e71b738f03d8ca12).
- Interface to list patients using `OpenEMR API` has been added in the [commit#Updated Patient List View](https://github.com/openemr/app-flutter-openemr/commit/0f0eaf9ca19af746fffd8a54cd08bf7a2cc92d0d).
- Prototype for `Heartbeat Measurement` was added in the [commit#Added Heart rate module](https://github.com/openemr/app-flutter-openemr/commit/a02d99af6b6e8e7971d8a6cd4d4fa5273b89a8e0) which was further improved in [commit#BPM optimized and logout functionality in side menu](https://github.com/openemr/app-flutter-openemr/commit/574b0532f807c6916289eaed4f4390458dd8140b).
- Firebase was first introduced for telehealth in the [commit#chat system using firebase](https://github.com/openemr/app-flutter-openemr/commit/e6273b11b4b1faf47d40672369a481b06dc5dc1a).
- Prototype for `ML Kit` can be found in the [commit#apply ml kit on home page](https://github.com/openemr/app-flutter-openemr/commit/8b7d27dffc875d5e85fa952e22ca63d8844cb2a4), it was further removed and only a part of it used for OCR.
- Other commits include different experiments done before finalizing a perfect one for the use.

openemr/app-golang-openemr
- An endpoint for OCR has been added in [commit:initial commit](https://github.com/openemr/app-golang-openemr/commit/2d4dfcc43156e7bae09eb0bf103a529ad9d0d1d1) which uses `tesseract-ocr`.

*Note: Commits made under this phase are either experimental or prototype, hence I would not advise using any part of it for production.
### Phase 2
In this Phase, I mainly worked on developing a production-ready front-end as the previous one was just a prototype. Also, the WebRTC prototype is added.

The commits are as follows:

openemr/app-flutter-openemr
- Under [commit#UI Changed](https://github.com/openemr/app-flutter-openemr/commit/4ef2846d824a88311d9e54eae71fe0aece944629), WebRTC prototype was added which was further improved in [webrtc fixed](https://github.com/openemr/app-flutter-openemr/commit/a44314507409ed9f71dbd8267c4aae8cd3dee585).
- Final UI was developed which was pushed at the start of Phase 3.

### Phase 3
All the prototype were cleared and production-ready implementation was added for the same along with the UI created in `phase 2`.

The commits are as follows:

openemr/app-flutter-openemr
- In [commit#UI Changed](https://github.com/openemr/app-flutter-openemr/commit/4ef2846d824a88311d9e54eae71fe0aece944629), the first production-ready app with the new UI was released and all the prototype code was removed.
- Then, OpenEMR endpoint was added back with updated UI and additional functionality(patient history and starred patients) in [commit#Patient can be starred and history is being stored](https://github.com/openemr/app-flutter-openemr/commit/31697ba59692b5cccb5f76707ea74b18a39dc2cc).
- Firebase with all the functionality and documentation was added by end of [commit#firebase chat is enabled](https://github.com/openemr/app-flutter-openemr/commit/071f154dc7d62f6ecc5d597810c014d920bdf503).
- Media sharing during the chat was added in [commit#image sharing support](https://github.com/openemr/app-flutter-openemr/commit/e31e26ab678d5743e5075ef43f8cdc50ca4f8716).
- `openemr/app-golang-openemr` repo was added as submodule under [commit#added submodule](https://github.com/openemr/app-flutter-openemr/commit/c32895dab4d0fa357719ef0d886ce2ed0e17b908) which is used by Video calling feature introduced in [commit#Added calling feature along with text recognition](https://github.com/openemr/app-flutter-openemr/commit/93d99d224197422bdf8ac9f96f813ad70c7008dc).
- [commit#New API support](https://github.com/openemr/app-flutter-openemr/commit/2e11c298593a176e6965dee8c1adf74a502173c5) added the support for API for new OpenEMR.
- Finally, under [commit#Minor Improvements](https://github.com/openemr/app-flutter-openemr/commit/509a535cb0713c89e8742c7a1de64ddce2a1a2bf), the code was cleared and the error message has been improved.

openemr/app-golang-openemr
- `WebRTC server` has been added in [commit#webrtc added](https://github.com/openemr/app-golang-openemr/commit/c6930bb8f84e572234daaa071add316334a247f5) which acts as a back-end for calling feature in the OpenEMR app.

## Future Work
`2.1.1` -> Current Version  
`2.1.2` -> Add Loading screens  
`2.1.3` -> Remove deprecated function  
`2.1.4` -> Error message based on API response  
`2.2` -> Medicine recognition  
`3.0` -> All OpenEMR API will be support

----

I thank my mentors and all the members of OpenEMR for being such an awesome and supportive community, it was a great summer of challenges, learning, and a lot of fun.

----

