import requests
import csv
import sys

def get_hooks(extension_name):
    try:
        extension_url = f"https://raw.githubusercontent.com/wikimedia/mediawiki-extensions-{extension_name}/master/extension.json"
        response = requests.get(extension_url)
        response.raise_for_status()  # Raise an exception for bad HTTP responses
        data = response.json()

        # Extract hook names from the "Hooks" map
        hooks = data.get("Hooks", {})

        return [(extension_name, hook) for hook in hooks.keys()]
    except requests.exceptions.RequestException as err:
        print(f"Request Exception: {err}")
    return None

def process_extensions(input_file, output_file):
    with open(input_file, 'r') as infile, open(output_file, 'w', newline='') as outfile:
        csv_writer = csv.writer(outfile)
        csv_writer.writerow(['Extension Name', 'Hook Name'])

        for line in infile:
            line = line.strip()

            # Skip blank lines and lines starting with "#"
            if not line or line.startswith("#"):
                continue

            extension_name = line

            # Print status message
            print(f"Fetching hook usage for {extension_name}")

            hooks = get_hooks(extension_name)

            if hooks is None:
                continue

            for row in hooks:
                csv_writer.writerow(row)

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python list-hook-usage.py input_file output_file")
    else:
        input_file_path = sys.argv[1]
        output_file_path = sys.argv[2]
        process_extensions(input_file_path, output_file_path)
