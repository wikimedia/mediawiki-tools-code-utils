from re import search
from argparse import ArgumentParser

import requests
import csv
import sys

def get_extension_json(prefix, name):
    try:
        url = f"https://raw.githubusercontent.com/wikimedia/mediawiki-{prefix}s-{name}/master/{prefix}.json"
        response = requests.get(url)
        response.raise_for_status()  # Raise an exception for bad HTTP responses
        data = response.json()

        return data
    except requests.exceptions.RequestException as err:
        print(f"Request Exception: {err}")
    return None


def get_hook_rows(extension_json):
    # Extract hook names from the "Hooks" map
    name = extension_json.get("name")
    hooks = extension_json.get("Hooks", {})
    rows = [ (name, hook) for hook in hooks ]

    return rows

def get_key_rows(extension_json):
    # Extract keys from extension.json dict
    name = extension_json.get("name")
    rows = [ (name, key) for key in extension_json.keys() ]

    return rows

def generate_output(extension_json, csv_writer, options):
    if options.hooks:
        rows = get_hook_rows(extension_json)
    else:
        rows = get_key_rows(extension_json)

    for row in rows:
        csv_writer.writerow(row)

def generate_header(csv_writer, options):
    if options.hooks:
        csv_writer.writerow(['Extension Name', 'Hook Name'])
    else:
       csv_writer.writerow(['Extension Name', 'Registration Field'])

def process_extensions(input_file, output_file, options):
    is_skin = options.skins
    infile = sys.stdin if input_file == '-' else open(input_file, 'r')
    outfile = sys.stdout if output_file == '-' else open(output_file, 'w', newline='')

    try:
        csv_writer = csv.writer(outfile)
        generate_header(csv_writer, options)
 
        extension_names = []
        for line in infile:
            line = line.strip()
            if line and not line.startswith("#"):
                # Extract extension/skin name from the given format or use the line directly
                match_extension = search(r'\$IP/extensions/([^/]+)/extension\.json', line)
                match_skin = search(r'\$IP/skins/([^/]+)/skin\.json', line)

                if match_extension:
                    if not is_skin:
                        name = match_extension.group(1)
                        extension_names.append(('extension', name))
                elif match_skin:
                    name = match_skin.group(1)
                    extension_names.append(('skin', name))
                else:
                    parts = line.split(':')
                    if len(parts) == 2 and parts[0] in ['extension', 'skin']:
                        if parts[0] == 'extension' and not is_skin:
                            extension_names.append((parts[0], parts[1]))
                        elif parts[0] == 'skin':
                            extension_names.append((parts[0], parts[1]))
                    else:
                        name = line
                        determined_prefix = 'skin' if is_skin else 'extension'
                        extension_names.append((determined_prefix, name))

        for prefix, name in extension_names:
            if outfile != sys.stdout:
                print(f"Fetching extension.json for {prefix}:{name}")

            extension_json = get_extension_json(prefix, name)

            if extension_json is None:
                continue

            generate_output(extension_json, csv_writer, options)
    finally:
        if infile != sys.stdin:
            infile.close()
        if outfile != sys.stdout:
            outfile.close()

def main():
    parser = ArgumentParser(description='Survey MediaWiki extensions.')
    parser.add_argument('input_file', help='Path to the input file or "-" for stdin. Must be a list of extensions.')
    parser.add_argument('output_file', help='Path to the output file or "-" for stdout. Will be a CSV file.')
    parser.add_argument('--skins', action='store_true', help='Look for skins instead of extensions')
    parser.add_argument('--hooks', action='store_true', help='List hook usage')

    args = parser.parse_args()
    process_extensions(args.input_file, args.output_file, args)

if __name__ == "__main__":
    main()