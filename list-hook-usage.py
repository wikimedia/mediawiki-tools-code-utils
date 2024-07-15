from argparse import ArgumentParser

import os
import extension_survey

def main():
    parser = ArgumentParser(description='List hook usage for MediaWiki extensions and skins.')
    parser.add_argument('input_file', help='Path to the input file or "-" for stdin. Must be a list of extensions.')
    parser.add_argument('output_file', help='Path to the output file or "-" for stdout. Will be a CSV file.')
    parser.add_argument('--skins', action='store_true', help='Look for skins instead of extensions')

    args = parser.parse_args()
    args.hooks = True

    extension_survey.process_extensions(args.input_file, args.output_file, args)

if __name__ == "__main__":
    main()