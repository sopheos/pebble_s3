# Pebble S3

Une petite couche PHP au-dessus du SDK AWS pour stocker et récupérer des objets compatibles S3.

Elle fournit une API simple pour créer un bucket, vérifier l'existence d'un objet, envoyer des chaînes, ressources ou fichiers, récupérer leurs métadonnées, les supprimer et les lister.

## Prérequis

- PHP 8.5 ou supérieur
- Un endpoint S3 et des identifiants AWS valides

## Installation

```bash
composer require sopheos/pebble_s3
```

Le paquet installe automatiquement `aws/aws-sdk-php`.

## Configuration

Créez un client du SDK AWS avec les paramètres adaptés à votre fournisseur S3 :

```php
use Aws\S3\S3Client;
use Pebble\S3\Store;

$client = new S3Client([
    'version' => 'latest',
    'region' => 'eu-west-3',
    'credentials' => [
        'key' => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
    ],
]);

$store = (new Store())
    ->setClient($client)
    ->setBucket('mon-bucket');
```

Pour un endpoint S3 compatible (MinIO, Scaleway, etc.), ajoutez notamment `endpoint` et, selon le fournisseur, `use_path_style_endpoint` à la configuration du client AWS.

## Opérations

Une fois le client configuré sur le `Store`, les méthodes disponibles sont les suivantes.

### Buckets et existence

```php
// Crée le bucket s'il n'existe pas. Retourne true s'il a été créé.
$created = $store->createBucket();

// Vérifie la présence d'un objet.
$exists = $store->has('documents/rapport.pdf');
```

### Envoyer un objet

```php
// Envoyer le contenu d'une chaîne ou d'une ressource PHP.
$store->set('notes/bonjour.txt', 'Bonjour !', 'text/plain');

// Envoyer un fichier local ; son type MIME est détecté automatiquement.
$store->setFile('images/logo.png', '/chemin/vers/logo.png');
```

`set()` accepte uniquement une chaîne ou une ressource. `setFile()` exige un chemin vers un fichier existant.

### Lire un objet

```php
$item = $store->get('notes/bonjour.txt');

if ($item !== null) {
    $stream = $item->body();       // Stream PSR-7, rembobiné par défaut
    $type = $item->type;           // par exemple : text/plain
    $length = $item->length;       // taille en octets
    $timestamp = $item->date;      // date de modification Unix
    $etag = $item->etag;
}
```

`get()` retourne `null` si la clé est absente. Pour obtenir une ressource PHP native, utilisez `$item->createResource()` ; cette opération détache le flux PSR-7.

### Supprimer et lister

```php
// La suppression d'une clé absente est considérée comme réussie.
$store->delete('notes/bonjour.txt');

// Renvoie les clés en erreur ; les suppressions sont regroupées par lots de 1 000.
$failedKeys = $store->deleteMany(['a.txt', 'b.txt']);

// Itère sur toutes les clés, éventuellement sous un préfixe.
foreach ($store->list('images/') as $key) {
    echo $key, PHP_EOL;
}
```

## Gestion des erreurs

Les erreurs renvoyées par S3 sont propagées sous forme de `Aws\Exception\AwsException`, à l'exception des objets absents traités par `has()`, `get()` et `delete()`. Les entrées invalides des méthodes d'écriture génèrent une `InvalidArgumentException`.

## Licence

Distribué sous licence [MIT](LICENSE).
