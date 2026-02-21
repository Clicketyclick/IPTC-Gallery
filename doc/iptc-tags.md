# IPTC tags

Source: https://www.picmeta.com/metadata/iptc-tags.htm

## Reference

- https://en.wikipedia.org/wiki/IPTC_Information_Interchange_Model
- https://en.wikipedia.org/wiki/Extensible_Metadata_Platform


## IIM Application Record 2
|
---|---
RecordVersion	|A binary number identifying the version of the Information Interchange Model, Part II, utilised by the provider. Version numbers are assigned by IPTC and NAA organizations.
ObjectType	|The Object Type is used to distinguish between different types of objects within the IIM. The first part is a number representing a language independent international reference to an Object Type followed by a colon separator. The second part, if used, is a text representation of the Object Type Number consisting of graphic characters plus spaces either in English or in the language of the service as indicated in tag <LanguageIdentifier>.
ObjectAttribute	|The Object Attribute defines the nature of the object independent of the Subject. The first part is a number representing a language independent international reference to an Object Attribute followed by a colon separator. The second part, if used, is a text representation of the Object Attribute Number consisting of graphic characters plus spaces either in English, or in the language of the service as indicated in tag <LanguageIdentifier>.
ObjectName, DocumentTitle	|Used as a shorthand reference for the object. Changes to existing data, such as updated stories or new crops on photos, should be identified in tag <EditStatus>.
EditStatus	|Status of the object data, according to the practice of the provider.
EditorialUpdate	|Indicates the type of update that this object provides to a previous object. The link to the previous object is made using the tags <ARMIdentifier> and <ARMVersion>, according to the practices of the provider.
Urgency	|Specifies the editorial urgency of content and not necessarily the envelope handling priority (see tag <EnvelopePriority>). The 1 is most urgent, 5 normal and 8 denotes the least-urgent copy.
Subject	|The Subject Reference is a structured definition of the subject matter.
Category	|Identifies the subject of the object data in the opinion of the provider. A list of categories will be maintained by a regional registry, where available, otherwise by the provider.
SupplementalCategory	|Supplemental categories further refine the subject of an object data. A supplemental category may include any of the recognised categories as used in tag <Category>. Otherwise, selection of supplemental categories are left to the provider.
FixtureId	|Identifies object data that recurs often and predictably. Enables users to immediately find or recall such an object.
Keywords	|Used to indicate specific information retrieval words. It is expected that a provider of various types of data that are related in subject matter uses the same keyword, enabling the receiving system or subsystems to search across all types of data for related material.
LocationCode	|Indicates the code of a country/geographical location referenced by the content of the object. Where ISO has established an appropriate country code under ISO 3166, that code will be used. When ISO 3166 does not adequately provide for identification of a location or a country, e.g. ships at sea, space, IPTC will assign an appropriate three-character code under the provisions of ISO 3166 to avoid conflicts.
LocationName	|Provides a full, publishable name of a country/geographical location referenced by the content of the object, according to guidelines of the provider.
ReleaseDate	|Designates in the form CCYYMMDD the earliest date the provider intends the object to be used. Follows ISO 8601 standard.
ReleaseTime	|Designates in the form HHMMSS:HHMM the earliest time the provider intends the object to be used. Follows ISO 8601 standard.
ExpirationDate	|Designates in the form CCYYMMDD the latest date the provider or owner intends the object data to be used. Follows ISO 8601 standard.
ExpirationTime	|Designates in the form HHMMSS:HHMM the latest time the provider or owner intends the object data to be used. Follows ISO 8601 standard.
SpecialInstructions	|Other editorial instructions concerning the use of the object data, such as embargoes and warnings.
ActionAdvised	|Indicates the type of action that this object provides to a previous object. The link to the previous object is made using tags <ARMIdentifier> and <ARMVersion>, according to the practices of the provider.
ReferenceService	|Identifies the Service Identifier of a prior envelope to which the current object refers.
ReferenceDate	|Identifies the date of a prior envelope to which the current object refers.
ReferenceNumber	|Identifies the Envelope Number of a prior envelope to which the current object refers.
DateCreated	|Represented in the form CCYYMMDD to designate the date the intellectual content of the object data was created rather than the date of the creation of the physical representation. Follows ISO 8601 standard.
TimeCreated	|Represented in the form HHMMSS:HHMM to designate the time the intellectual content of the object data current source material was created rather than the creation of the physical representation. Follows ISO 8601 standard.
DigitizationDate	|Represented in the form CCYYMMDD to designate the date the digital representation of the object data was created. Follows ISO 8601 standard.
DigitizationTime	|Represented in the form HHMMSS:HHMM to designate the time the digital representation of the object data was created. Follows ISO 8601 standard.
Program	|Identifies the type of program used to originate the object data.
ProgramVersion	|Used to identify the version of the program mentioned in tag <Program>.
ObjectCycle	|Used to identify the editorial cycle of object data.
Byline, Author	|Contains name of the creator of the object data, e.g. writer, photographer or graphic artist.
BylineTitle	|A by-line title is the title of the creator or creators of an object data. Where used, a by-line title should follow the by-line it modifies.
City	|Identifies city of object data origin according to guidelines established by the provider.
SubLocation	|Identifies the location within a city from which the object data originates, according to guidelines established by the provider.
ProvinceState	|Identifies Province/State of origin according to guidelines established by the provider.
CountryCode	|Indicates the code of the country/primary location where the intellectual property of the object data was created, e.g. a photo was taken, an event occurred. Where ISO has established an appropriate country code under ISO 3166, that code will be used. When ISO 3166 does not adequately provide for identification of a location or a new country, e.g. ships at sea, space, IPTC will assign an appropriate three-character code under the provisions of ISO 3166 to avoid conflicts.
CountryName, Country	|Provides full, publishable, name of the country/primary location where the intellectual property of the object data was created, according to guidelines of the provider.
TransmissionReference	|A code representing the location of original transmission according to practices of the provider.
Headline	|A publishable entry providing a synopsis of the contents of the object data.
Credit	|Identifies the provider of the object data, not necessarily the owner/creator.
Source	|Identifies the original owner of the intellectual content of the object data. This could be an agency, a member of an agency or an individual.
Copyright	|Contains any necessary copyright notice.
Contact	|Identifies the person or organisation which can provide further background information on the object data.
Caption, Description	|A textual description of the object data.
Writer	|Identification of the name of the person involved in the writing, editing or correcting the object data or caption/abstract.
RasterizedCaption	|Contains the rasterized object data description and is used where characters that have not been coded are required for the caption.
ImageType	|Indicates the color components of an image.
ImageOrientation	|Indicates the layout of an image.
Language	|Describes the major national language of the object, according to the 2-letter codes of ISO 639:1988. Does not define or imply any coded character set, but is used for internal routing, e.g. to various editorial desks.
AudioType	|Indicates the type of an audio content.
AudioRate	|Indicates the sampling rate in Hertz of an audio content.
AudioResolution	|Indicates the sampling resolution of an audio content.
AudioDuration	|Indicates the duration of an audio content.
AudioOutcue	|Identifies the content of the end of an audio object data, according to guidelines established by the provider.
PreviewFormat	|A binary number representing the file format of the object data preview. The file format must be registered with IPTC or NAA organizations with a unique number assigned to it.
PreviewVersion	|A binary number representing the particular version of the object data preview file format specified in tag <PreviewFormat>.
Preview	|Binary image preview data.
 

## IIM Envelope Record

|
---|---
ModelVersion	|A binary number identifying the version of the Information Interchange Model, Part I, utilised by the provider. Version numbers are assigned by IPTC and NAA organizations.
Destination	|This Dataset is to accommodate some providers who require routing information above the appropriate OSI layers.
FileFormat	|A binary number representing the file format. The file format must be registered with IPTC or NAA with a unique number assigned to it. The information is used to route the data to the appropriate system and to allow the receiving system to perform the appropriate actions there to.
FileVersion	|A binary number representing the particular version of the File Format specified by <FileFormat> tag.
ServiceId	|Identifies the provider and product.
EnvelopeNumber	|The characters form a number that will be unique for the date specified in <DateSent> tag and for the Service Identifier specified by <ServiceIdentifier> tag. If identical envelope numbers appear with the same date and with the same Service Identifier, records 2-9 must be unchanged from the original. This is not intended to be a sequential serial number reception check.
ProductId	|Allows a provider to identify subsets of its overall service. Used to provide receiving organisation data on which to select, route, or otherwise handle data.
EnvelopePriority	|Specifies the envelope handling priority and not the editorial urgency (see <Urgency> tag). 1 indicates the most urgent, 5 the normal urgency, and 8 the least urgent copy. The numeral 9 indicates a User Defined Priority. The numeral 0 is reserved for future use.
DateSent	|Uses the format CCYYMMDD (century, year, month, day) as defined in ISO 8601 to indicate year, month and day the service sent the material.
TimeSent	|Uses the format HHMMSS:HHMM where HHMMSS refers to local hour, minute and seconds and HHMM refers to hours and minutes ahead (+) or behind (-) Universal Coordinated Time as described in ISO 8601. This is the time the service sent the material.
CharacterSet	|This tag consisting of one or more control functions used for the announcement, invocation or designation of coded character sets. The control functions follow the ISO 2022 standard and may consist of the escape control character and one or more graphic characters.
UniqueNameObject, UNO	|This tag provide a globally unique identification for objects as specified in the IIM, independent of provider and for any media form. The provider must ensure the UNO is unique. Objects with the same UNO are identical.
ARMIdentifier	|The Dataset identifies the Abstract Relationship Method identifier (ARM) which is described in a document registered by the originator of the ARM with the IPTC and NAA organizations.
ARMVersion	|This tag consisting of a binary number representing the particular version of the ARM specified by tag <ARMId>.

---