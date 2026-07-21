---
title: OpenEMR 8.2 in the Cloud
author:
  name: "OpenEMR Foundation"
  twitter: openemr
date: '2026-07-21'
slug: openemr-8-2-cloud-release
images:
  - images/openemr.png
coverImage: images/openemr.png
twitterCardImageURL: images/openemr.png
---
# OpenEMR 8.2 in the Cloud

OpenEMR Express Plus now installs version 8.2.0 — the current, ONC-certified production release — on a modern, secure foundation, restoring true one-click deployment on AWS. The stack is built on HIPAA-eligible AWS services with encryption and auditing designed to support a HIPAA-compliant deployment when properly configured — including executing a Business Associate Agreement with AWS, which our deployment guide walks you through.

___[Launch OpenEMR Express Plus on AWS](https://console.aws.amazon.com/cloudformation/home?region=us-east-1#/stacks/new?stackName=OpenEMR&templateURL=https://s3.amazonaws.com/openemr-cfn-useast1/OpenEMR-Express-Plus.json)___

What's new under the hood:

* **OpenEMR 8.2.0**, deployed automatically with an embedded MariaDB 11.8 database.
* **Ubuntu 24.04 LTS**, always current: the template now resolves Canonical's latest supported image at launch time in every region, so deployments never start life on an outdated operating system.
* **Encryption done right, end to end.** All practice data — database, documents, images, and backups — lives on AWS KMS-encrypted storage, and encryption keys now deliberately outlive the server so your backups always remain recoverable.
* **Disaster recovery you can audit.** Daily encrypted backups upload automatically to S3, and the companion recovery template rebuilds a complete practice from those backups alone — even if the original server is gone. Restores now report exactly which point in time they recovered to.

Every piece of this release was validated the hard way: we deployed a practice, backed it up, deleted the entire server, and recovered it — patient data intact — from nothing but the retained encrypted backups.

Existing Express Plus users on earlier versions can move to a fresh 8.2 deployment using OpenEMR's built-in backup and the import tools in our [cloud repository](https://github.com/openemr/openemr-devops); step-by-step help is available from the community on the [OpenEMR Forum](https://community.open-emr.org/) or from our list of [professional support vendors](https://www.open-emr.org/wiki/index.php/Professional_Support).

Thank you to the community volunteers who tested, reviewed, and hardened this release — the collaborative effort is what keeps OpenEMR innovative, secure, and the best alternative to expensive proprietary systems.

## Support OpenEMR Certification and Beyond

We need your financial support in keeping OpenEMR open and free. Your donation funds critical work, including maintaining ONC certification compliance, supporting API FHIR infrastructure, and advancing innovative features.

**If you rely on OpenEMR for your practice or support its open-source mission, please donate today.** Offset certification costs with a ___[one-time contribution](https://www.open-emr.org/donate/)___, or become an official OpenEMR Sponsor with a ___[recurring monthly donation](https://www.open-emr.org/donate/)___.
