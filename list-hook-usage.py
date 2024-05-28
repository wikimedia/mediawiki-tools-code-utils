from re import search
from argparse import ArgumentParser

import requests
import csv
import sys


def get_hooks(prefix, name):
    try:
        url = f"https://raw.githubusercontent.com/wikimedia/mediawiki-{prefix}s-{name}/master/{prefix}.json"
        response = requests.get(url)
        response.raise_for_status()  # Raise an exception for bad HTTP responses
        data = response.json()

        # Extract hook names from the "Hooks" map
        hooks = data.get("Hooks", {})

        return [(name, hook) for hook in hooks.keys()]
    except requests.exceptions.RequestException as err:
        print(f"Request Exception: {err}")
    return None


def process_extensions(input_file, output_file, is_skin):
    infile = sys.stdin if input_file == '-' else open(input_file, 'r')
    outfile = sys.stdout if output_file == '-' else open(output_file, 'w', newline='')

    try:
        csv_writer = csv.writer(outfile)
        csv_writer.writerow(['Extension Name', 'Hook Name'])

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
                print(f"Fetching hook usage for {prefix}:{name}")

            hooks = get_hooks(prefix, name)

            if hooks is None:
                continue

            for row in hooks:
                csv_writer.writerow(row)
    finally:
        if infile != sys.stdin:
            infile.close()
        if outfile != sys.stdout:
            outfile.close()


if __name__ == "__main__":
    parser = ArgumentParser(description='List hook usage for MediaWiki extensions and skins.')
    parser.add_argument('input_file', help='Path to the input file or "-" for stdin')
    parser.add_argument('output_file', help='Path to the output file or "-" for stdout')
    parser.add_argument('--skins', action='store_true', help='Look for skins instead of extensions')

    args = parser.parse_args()
    process_extensions(args.input_file, args.output_file, args.skins)
