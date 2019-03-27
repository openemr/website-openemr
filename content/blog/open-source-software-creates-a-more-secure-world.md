---
title: "Open Source Software Creates a More Secure World"
author:
  name: OpenEMR
  twitter: openemr
date: 2019-03-27T01:32:18-05:00
draft: true
slug: open-source-creates-secure-world
images:
  - images/cyber.jpg
coverImage: images/cyber.jpg
coverImageStyle: full
---
Open-source makes up 35% of source code of commercial applications and can be very secure, and regardless, it must be made secure in a future world that hopes for “cyber peace” instead of cyber war.
<!--more-->

## What's wrong with non-open-source software?
Before we begin, though, why is software from most companies not secure? First, there are almost no economic incentives for security today in the for-profit sector. The preferences of consumers, until very recently, never demanded security. The legal precedent for punishing data breaches or IT negligence is non-existent, as shown by the 11th circuit overruling the FTC’s case against Quest Diagnostics in June of 2019.

Someday this will change and companies have a crucial role to play in the future, especially once new laws, liability concepts, and moral frameworks begin to appear. 

## Accountability
But why exactly is open-source more secure today? First, there is accountability. Most open-source repos have their source code publically available on websites like Github, and such platforms allow for incredibly detailed analysis. You can see who added a specific line of code seven years ago, what the comments of other developers were about it, and actually download the exact version of the code to run it for testing. A lot of times security issues are identified by outside researchers. When they contact companies, they are dismissed and sometimes even sued. This would run counter to most of the ethos in the open-source community, and additionally, the detailed evidence trail (generally not publicly available for closed-source applications), along with their lack of major lawyer money, makes open-source projects much more willing partners in securing applications. Of course, this only prevents some security issues, but it adds up.

## Many eyes
Second, many eyes are looking, or could be potentially looking, at the source code of applications. I would refer to this as “ease of access”. Full versions of the software are available for download and dynamic testing, along with static testing of the code. This allows a massively parallelized effort of developers in the United States and elsewhere, similar to multi-core CPUs, in tackling security issues. 

Additionally, near the beginning of the last paragraph, I mentioned “potentially” looking. This is very important and needs to be elaborated on. Captain Sally White, an Army officer and graduate student at Harvard University, published a paper in March of 2018 that discussed how Russia leveraged scores of private sector computer engineers as auxiliary troops during the war with Georgia in 2008. Workers still kept their day jobs but assisted the Russian Army, either for a few temporary weeks or during their off hours. If we reverse this concept, then such an ordering of people could also become a defensive tactic as well. Many eyes can look at and contribute to an OS project in a massively parallel fashion, so if there is ever a crisis of security in a particular application or even entire range of software, work could be done much faster and by many more people than a single firm with a private codebase sitting on their internal network. 

## Competition
Finally, there is a competitive nature to open-source. Given the work is volunteer-based, some people work shockingly hard because they have to justify any work on a much deeper level than to simply earn a paycheck. A good example here is the timeline involving Apache Struts and the Equifax breach. Apache was made aware of the vulnerability in mid-February, and a patch was released in early March. An exploit was made available for this vulnerability a day after the patch was released. Yet Equifax took until the first day of August to fix their Apache Struts server. In other words, the Apache Foundation had a 4-month:1-month time efficiency ratio against Equifax, despite having annual revenues of $900,000 vs over $3 billion. I’m not going to stretch out the math here, although I am sure the numbers would look even worse for Equifax, but the point remains that Apache has an “esprit de corps” that Equifax seems to simply lack. 

## Example
For some examples of actual software, I would use the case of Firefox vs Internet Explorer. First I would compare Firefox and Internet Explorer in the early 2000s. Open-source was on some levels much harder to do back then, and certainly remote work among scattered developers was, so I think the example is indicative. During this time, IE, and especially IE6, was infamous for disregarding open standards and trying to push their own internal framework. IE was more used, yes, but at the same time the source code was open for Firefox (making vulnerabilities easier to find), and Microsoft’s litigious nature likely means some were never submitted. For those interested, a similar trend exists in comparing SQL Server (victim of the infamous SQL Slammer worm) to open-source MySQL, the latter of which was considered secure enough that it was purchased by Sun Microsystems in an attempt to compete with the largest database company of all, Oracle Corporation.

Accountability and ethos, ease of access, and competition are why today open-source applications are more secure than their corporate counterparts, who lack almost any incentive from markets, institutions, or the courts to develop secure software. By no means is the future certain though, and unless open-source continues to innovate and begins to understand the seriousness of security in software, this could very well change as new court decisions are handed down and investors begin to shift away from insecure companies.

## Future
So what does the future look like for open-source, one where it can maintain the advantages it has while still becoming more secure?

First, there should be some high-level organization(s) that open-source projects can move under or associate with. These organizations could provide shared resources and guided advice regarding best security practices to open-source projects. Being a member of these organizations would have requirements as well, and open the projects to a level of audit by the organization. This would provide assurance to those using the projects code and further motivation for the projects to engage in good security practices. Such an organization could easily be funded by modest amounts from corporations, perhaps with a single one-time cost of $100 million. With conservative estimates of global cyber risk already reaching the multi-trillion dollar amount, such a cost seems bearable. Also if the money were provided up-front in the form of an endowment, corporations would have little say in the future about how the money was spent.

Second, there should be a group(this could be an extension of the organization above) that provides on-demand access to a group of skilled developers to fix critical CVEs. This group would need to be set up in a way not to be abused (e.g. offloading your entire security development onto this group). Good coders and architects are rare enough that any model that uses them cannot depend on full-time recruitment but must leverage some kind of an on-demand recruitment, perhaps borrowing strategies from startups that experimented with “gig-economy” type of workforce arrangements. We already see the beginnings of a platform like this is BugCrowd or HackerOne.

Third, so much work is done building software there often appears no time for documentation. Yet in the future, software projects can never be considered done unless there is an end-to-end documentation for what is produced, including the most-preferred security configurations. So much of software, OpenEMR included, might not be insecure intrinsically, but is insecure because of how it was set up. Ideally, it would be secure as possible out of the box, which should be something else open-source should focus on; but for the remaining parts that can’t be done pre-install, the documentation should be clear and easy to follow. The burden needs to be shifted away from the end-users as much as possible. 

Next, open-source groups should adopt policies that ensure their individual members are secure. What kind of routers do their members use at home? Have they configured their firewall? Do they have 2FA on their personal email? In their personal Github’s, do they leak important credentials? Today, project security depends on individuals personal security, and this could be codified in a few simple, easy to follow policies inside the Code of Conduct now being placed in most open-source projects.

Finally, better adoption of DevSecOps will be another critical component that ensures the future security of open-source. I laughed when I first heard this name, assuming it was a desperate attempt for a conference speaking slot, but it is in-fact a serious concept. Security unit tests, security integration tests, and security of the rest of the devops pipeline must be made as regular as DevOps is today. Continuous deployment must operate alongside “continuous security”. This has the plus of encouraging people to adopt DevOps in the first place, many of whom are still waiting to dip their toe in the water.

## Closing
There is a tremendous amount to do in securing the world’s open-source codebase. Sometimes it’s overwhelming when one looks at everything that needs to be done. That being said, while the work level is astounding, the solutions are relatively simple and often widely agreed-on. In a world with unanswerable questions like the meaning of truth, the limits of free speech, or the origins of violence, such a problem is to envied and stands to contain some of this decade’s most rewarding and impactful tasks for those who choose to help.

## About OpenEMR
OpenEMR is the most popular open-source electronic health records and medical practice management solution. It is maintained by a vibrant community of volunteers and support professionals. The system is 2014 ONC Certified as a Complete EHR, downloaded more than 5,000 times per month, and has an estimated usage by 100,000 medical providers across many borders and languages. As a leader in open-source healthcare software, OpenEMR provides an unparalleled and fully customizable experience for your healthcare facility.