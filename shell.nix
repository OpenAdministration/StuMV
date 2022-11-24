{ pkgs ? import <nixpkgs> {} }:

with pkgs;

mkShell {
  buildInputs = [
    php81
    php81Packages.composer
    nodePackages.npm
    nodejs
  ];
}
