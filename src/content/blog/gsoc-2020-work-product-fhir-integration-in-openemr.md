---
title: "GSoC 2020 Work Product: FHIR Integration in OpenEMR"
author:
  name: Yash Raj Bothra
  twitter: none
date: 2020-08-28T11:30:00-08:00
slug: gsoc-2020-work-product-fhir-integration-in-openemr
images:
  - images/gsoc-fhir.png
coverImage: images/gsoc-fhir.png
coverImageStyle: full
---
GSoC is about to finish. It was a very successful journey with OpenEMR and I’m very happy that I selected OpenEMR as my GSoC organization. First I should thank my mentors for providing me the guidance to successfully complete the GSoC project. Additionally, I am very thankful to all members of the OpenEMR community.
<!--more-->

## Overview

The major goal of the project was to integrate FHIR Standard API’s that will help in exchanging healthcare information electronically. My goal with this project was to complete the existing implementation and implement new resources to help OpenEMR get Meaningful Use Certification.
Initially, it was difficult to refactor the existing implementation and process all the data in a uniform way, but big thanks to Dixon Whitmire for helping me get a structured plan which I followed all the way along.

## Mentors

* Brady Miller
* Dixon Whitmire
* Jerry Padgett
* Ken Chapple
* Rob Hausam

## Objectives

* Uniform processing and Refactoring of FHIR requests **[Completed]**
* Support OpenEMR Web App for FHIR Resource **[Completed]**
* Introduce UUID to every resource **[Completed]**
* Introduce New API Endpoints to Support FHIR Resources **[Completed]**
* Improve Automated Test Coverage **[In-Progress]**

## GSoC 2020 Contributions

1. Encounter Resource
  * https://github.com/openemr/openemr/pull/3628
  * https://github.com/openemr/openemr/pull/3506
2. Practitioner Resource - https://github.com/openemr/openemr/pull/3652
3. Organization Resource - https://github.com/openemr/openemr/pull/3662
4. PractitionerRole Resource
   * https://github.com/openemr/openemr/pull/3680
   * https://github.com/openemr/openemr/pull/3709
   * https://github.com/openemr/openemr/pull/3713
5. Immunization Resource - https://github.com/openemr/openemr/pull/3729
6. AllergyIntolerence Resource - https://github.com/openemr/openemr/pull/3736
7. Condition Resource - https://github.com/openemr/openemr/pull/3763
8. Procedure Resource - https://github.com/openemr/openemr/pull/3784
9. Medication Resource - https://github.com/openemr/openemr/pull/3790
10. MedicationRequest Resource - https://github.com/openemr/openemr/pull/3821
11. Location Resource - https://github.com/openemr/openemr/pull/3829
12. Observation Resource - https://github.com/openemr/openemr/pull/3867
13. CareTeam Resource - https://github.com/openemr/openemr/pull/3884

**Documentation**

* https://github.com/openemr/openemr/blob/master/API_README.md
* https://github.com/openemr/openemr/blob/master/FHIR_README.md

**Pending Work**

* Still there is scope to add more and more FHIR resources.
* Most of the targeted resources are achieved as planned in the GSoC. Improving the test coverage and adding more operations are the areas that can be further enhanced.

**Experience**

GSoC 2020 is my first contribution to an open source software project. It was really amazing, and I gained massive experience. I saw firsthand how an open source community builds an open source project and developed an appreciation for open source culture. Over these past few months I have improved myself in programming skills, communication skills and several other skills. I would like to thank my mentor Brady Miller and all the other mentors for their guidance and support. I would like to thank the OpenEMR community for accepting me and helping me. Finally, I would like to thank Google for organizing the GSoC program.
<br>
<br>
