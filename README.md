# espoo-valtuustobot // Espoo municipal parliament bot

NOTE: This file is out date as of spring 2018, when Espoo municipality updated their document services.

Automated municipal advocacy bot.

The script is a crawler for Espoo (a municipality next to Helsinki, Finland) document server, that follows the document root and searches for changes in directories for boards responsible for eg. education or building. The script is intended to be run daily, and any new documents (in this case: upcoming meeting agendas) are sent via email to the specified recipient list. This enables reacting to upcoming meetings the moment any materials are put out for future meetings.

## Installation

Clone the repository to your server. Create a init.json file in the root, containing the following fields:

´´´
{
'target_url' : 'which_url_to_look_at',
'slug' : 'data_folder_for_document_storing',
'recipients' : ['list_of', 'emails_for', 'recipients']
'admin' : 'email_of_administrator'
}
´´´

Set the _process_all_meetings.php_ file to be run at specified times, eg. daily at 9am. The script will crawl through the document database and check for any new documents. Note that for the first time the script will send email for all of the documents, as they are considered new. Consider runnin the first passes with a recipient email address that can expect some 200 new emails.

(C) Oskar Niemenoja 2017
contact: contact@oskarniemenoja.fi
